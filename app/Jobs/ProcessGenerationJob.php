<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Lead;
use App\Models\Upload;
use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;
use App\Services\AI\Resolvers\ProviderResolver;
use App\Services\Billing\CreditLedgerService;
use App\Services\Generation\FinalGenerationSmsService;
use App\Services\Generation\GenerationStageCatalog;
use App\Services\GenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Processes one generation stage.
 *
 * @since 1.0.0
 */
class ProcessGenerationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesAndRestoresModelIdentifiers;
    use SerializesModels;

    /**
     * Keep external image provider calls from being killed too early.
     */
    public int $timeout = 960;

    /**
     * Never repeat paid provider requests automatically.
     */
    public int $tries = 1;

    public function __construct(public GenerationJob $generationJob) {}

    /**
     * Execute provider processing and persist the generated asset.
     */
    public function handle(): void
    {
        $scope = 'generation.job.process';

        try {
            $this->generationJob->refresh();

            if ($this->generationJob->status === 'completed' || $this->generationJob->asset()->exists()) {
                $this->generationJob->update([
                    'status' => 'completed',
                    'completed_at' => $this->generationJob->completed_at ?? now(),
                    'last_error' => null,
                ]);

                $this->completeGroupOrDispatchNext();

                return;
            }

            if ($this->shouldStopBecauseGroupFailed()) {
                return;
            }

            $attempt = ((int) $this->generationJob->attempts) + 1;

            $this->generationJob->update([
                'status' => 'processing',
                'started_at' => now(),
                'attempts' => $attempt,
                'last_error' => null,
            ]);

            $upload = $this->generationJob->upload;
            $lead = $this->generationJob->lead;

            if (! $upload instanceof Upload) {
                throw new RuntimeException('Generation upload could not be resolved.');
            }

            if (! $lead instanceof Lead) {
                throw new RuntimeException('Generation lead could not be resolved.');
            }

            if ($this->generationJob->aiModelAccess) {
                $this->processWithAiModelAction($scope, $upload);

                return;
            }

            $this->processWithLegacyProvider($scope, $upload, $lead);
        } catch (Throwable $exception) {
            $this->safelyMarkFailedFromException($exception);

            throw $exception;
        }
    }

    /**
     * Laravel queue failure callback.
     */
    public function failed(Throwable $exception): void
    {
        $this->safelyMarkFailedFromException($exception);
    }

    /**
     * Process the stage through the configured AI model action.
     */
    private function processWithAiModelAction(string $scope, Upload $upload): void
    {
        try {
            $response = app(RunAiModelAction::class)->execute(
                clinic: $this->generationJob->tenant,
                usageCategoryKey: $this->generationJob->aiModelAccess->usage_category_key,
                payload: [
                    'generation_job_id' => $this->generationJob->uuid,
                    'upload_id' => $upload->uuid,
                    'upload_disk' => $upload->original_disk,
                    'upload_path' => $upload->original_path,
                    'period' => $this->generationJob->period,
                    'prompt' => $this->generationJob->prompt,
                ],
                selectedAccess: $this->generationJob->aiModelAccess,
            );

            if (! $response->successful) {
                $this->markFailed($response->errorCode ?: 'provider_failed');

                return;
            }

            if (! $this->aiResponseHasImage($response)) {
                $this->markFailed('missing_generated_image');

                return;
            }

            if ($this->shouldStopBecauseGroupFailed()) {
                $this->markFailed('group_already_failed');

                return;
            }

            if (! $this->consumeServiceCredit()) {
                return;
            }

            $this->storeGeneratedBinaryAsset($response);
            $this->markCompletedAndContinue();
        } catch (Throwable $exception) {
            $this->markFailed('ai_model_exception', $exception);
        }
    }

    /**
     * Process the stage through the legacy provider path.
     */
    private function processWithLegacyProvider(string $scope, Upload $upload, Lead $lead): void
    {
        try {
            $resolver = new ProviderResolver;
            $provider = $resolver->resolveForTenant($this->generationJob->tenant_id);

            $request = new ImageGenerationRequest(
                tenantUuid: $lead->tenant->uuid,
                leadUuid: $lead->uuid,
                uploadPath: $upload->original_path,
                period: $this->generationJob->period,
                prompt: $this->generationJob->prompt,
                modelName: $this->generationJob->model_name,
                settings: $this->generationJob->aiModelAccess?->settings ?? [],
            );

            $response = $provider->submit($request);

            if ($response->status !== 'completed' || ! $response->localPath) {
                $this->markFailed('legacy_provider_incomplete');

                return;
            }

            if ($this->shouldStopBecauseGroupFailed()) {
                $this->markFailed('group_already_failed');

                return;
            }

            if (! $this->consumeServiceCredit()) {
                return;
            }

            $this->storeGeneratedLocalAsset($response);
            $this->markCompletedAndContinue();
        } catch (Throwable $exception) {
            $this->markFailed('legacy_provider_exception', $exception);
        }
    }

    /**
     * Mark the stage as completed and continue the group.
     */
    private function markCompletedAndContinue(): void
    {
        $scope = 'generation.job.process';

        $this->generationJob->refresh();

        $this->generationJob->update([
            'status' => 'completed',
            'completed_at' => now(),
            'last_error' => null,
        ]);

        $this->completeGroupOrDispatchNext();
    }

    /**
     * Complete the group or dispatch the next stage.
     */
    private function completeGroupOrDispatchNext(): void
    {
        $scope = 'generation.job.process';

        $group = $this->generationJob->group()->with('jobs')->firstOrFail();
        $wasCompleted = $group->status === 'completed';

        $completedCount = $group->jobs
            ->filter(fn (GenerationJob $job): bool => $job->status === 'completed')
            ->count();

        if ($group->jobs->contains(fn (GenerationJob $job): bool => $job->status === 'failed')) {
            $this->failGroupAfterJobFailure($group);

            return;
        }

        if ($group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed')) {
            $group->update(['status' => 'completed']);

            if (! $wasCompleted) {
                app(FinalGenerationSmsService::class)->sendCompleted($group->refresh());

            }

            return;
        }

        $group->update(['status' => 'processing']);

        app(GenerationService::class)->dispatchNextStage($group);

    }

    /**
     * Mark the stage and group as failed.
     */
    private function markFailed(string $reasonCode, ?Throwable $exception = null): void
    {
        $scope = 'generation.job.process';
        $message = $this->publicFailureMessage();

        $this->generationJob->refresh();

        $this->generationJob->update([
            'status' => 'failed',
            'last_error' => $message,
        ]);

        $this->logJobFailure($reasonCode, $exception);

        $group = $this->generationJob->group()->with('jobs')->first();

        if ($group) {
            $this->failGroupAfterJobFailure($group);
        }

    }

    /**
     * Stop remaining stages after any generation stage fails.
     */
    private function failGroupAfterJobFailure(GenerationGroup $group): void
    {
        $message = $this->publicFailureMessage();

        $group->jobs()
            ->whereKeyNot($this->generationJob->id)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'cancelled',
                'last_error' => null,
                'updated_at' => now(),
            ]);

        $group->update(['status' => 'failed']);
    }

    /**
     * Stop this queued stage before provider execution when another stage already failed.
     */
    private function shouldStopBecauseGroupFailed(): bool
    {
        $group = $this->generationJob->group()->with('jobs')->first();

        if (! $group instanceof GenerationGroup) {
            return false;
        }

        $failed = $group->status === 'failed'
            || $group->jobs->contains(fn (GenerationJob $job): bool => $job->status === 'failed');

        if (! $failed) {
            return false;
        }

        if ($this->generationJob->status !== 'failed') {
            $this->generationJob->update([
                'status' => 'cancelled',
                'last_error' => null,
            ]);
        }

        return true;
    }

    /**
     * Mark failed from an exception without leaving the job stuck in processing.
     */
    private function safelyMarkFailedFromException(Throwable $exception): void
    {
        try {
            $this->markFailed('exception', $exception);
        } catch (Throwable $failureException) {
            Log::error('[generation.job.process] Could not mark generation job as failed', [
                'generation_job_id' => $this->generationJob->id ?? null,
                'generation_job_uuid' => $this->generationJob->uuid ?? null,
                'generation_group_id' => $this->generationJob->generation_group_id ?? null,
                'original_exception' => $exception::class,
                'original_message' => $exception->getMessage(),
                'failure_exception' => $failureException::class,
                'failure_message' => $failureException->getMessage(),
            ]);
        }
    }

    /**
     * Return the generic public failure message stored for visitor-facing APIs.
     */
    private function publicFailureMessage(): string
    {
        return __('ai_models.errors.provider_failed');
    }

    /**
     * Log operational generation failures independently from debug tracing.
     */
    private function logJobFailure(string $reasonCode, ?Throwable $exception = null): void
    {
        $group = $this->generationJob->relationLoaded('group')
            ? $this->generationJob->group
            : $this->generationJob->group()->first();

        Log::warning('Generation job failed.', [
            'generation_job_id' => $this->generationJob->id,
            'generation_job_uuid' => $this->generationJob->uuid,
            'generation_group_id' => $this->generationJob->generation_group_id,
            'generation_group_uuid' => $group?->uuid,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'period' => $this->generationJob->period,
            'model_key' => $this->generationJob->aiModelAccess?->model_key ?? $this->generationJob->model_name,
            'ai_model_access_id' => $this->generationJob->ai_model_access_id,
            'reason_code' => $reasonCode,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    /**
     * Consume one service credit before storing the generated output.
     */
    private function consumeServiceCredit(): bool
    {
        $scope = 'generation.job.process';
        $serviceKey = $this->generationJob->aiModelAccess?->usage_category_key ?? 'hair_transplant';

        try {
            app(CreditLedgerService::class)->consume(
                tenant: $this->generationJob->tenant,
                serviceKey: $serviceKey,
                related: $this->generationJob,
                description: __('credits.descriptions.ai_usage', [
                    'period' => app(GenerationStageCatalog::class)->label($this->generationJob->period),
                ]),
            );

        } catch (ValidationException $exception) {
            $this->markFailed('credit_validation_failed', $exception);

            return false;
        } catch (Throwable $exception) {
            $this->markFailed('credit_consume_failed', $exception);

            return false;
        }

        return true;
    }

    /**
     * Check whether the AI response contains image bytes.
     */
    private function aiResponseHasImage(AiModelResponseData $response): bool
    {
        return is_string($response->output['binary_content'] ?? null)
            && ($response->output['binary_content'] ?? '') !== '';
    }

    /**
     * Copy local provider output into private result storage.
     */
    private function storeGeneratedLocalAsset(ImageGenerationResponse $response): void
    {
        $scope = 'generation.job.process';

        $source = Storage::disk('local');
        $sourcePath = $response->localPath;
        $destination = $this->destinationPath('jpg');

        if (! $source->exists($sourcePath)) {
            throw new RuntimeException('Generated local provider file does not exist.');
        }

        $source->makeDirectory(dirname($destination));
        $source->copy($sourcePath, $destination);

        $size = $source->size($destination);
        $mime = is_string($response->metadata['mime'] ?? null)
            ? $response->metadata['mime']
            : 'image/jpeg';

        $this->createAsset([
            'disk' => 'local',
            'path' => $destination,
            'mime' => $mime,
            'size' => $response->metadata['size'] ?? $size,
            'provider_metadata_json' => $response->metadata,
        ]);
    }

    /**
     * Store binary provider output into private result storage.
     */
    private function storeGeneratedBinaryAsset(AiModelResponseData $response): void
    {
        $scope = 'generation.job.process';

        $extension = (string) ($response->output['extension'] ?? 'jpg');
        $mime = (string) ($response->output['mime'] ?? 'image/jpeg');
        $disk = Storage::disk('local');
        $destination = $this->destinationPath($extension);

        $disk->makeDirectory(dirname($destination));
        $disk->put($destination, $response->output['binary_content']);

        $size = $disk->size($destination);
        $safeMime = str_starts_with($mime, 'image/') ? $mime : 'image/jpeg';

        $this->createAsset([
            'disk' => 'local',
            'path' => $destination,
            'mime' => $safeMime,
            'size' => $size,
            'provider_metadata_json' => $response->metadata,
        ]);
    }

    /**
     * Create the generated asset database record.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createAsset(array $attributes): void
    {
        $scope = 'generation.job.process';

        if ($this->generationJob->asset()->exists()) {
            return;
        }

        $asset = GeneratedAsset::create([
            'generation_group_id' => $this->generationJob->generation_group_id,
            'generation_job_id' => $this->generationJob->id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'period' => $this->generationJob->period,
        ] + $attributes);

    }

    /**
     * Build the private result storage path.
     */
    private function destinationPath(string $extension): string
    {
        $extension = ltrim(strtolower($extension), '.');
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';

        return 'tenants/'.$this->generationJob->tenant->uuid
            .'/leads/'.$this->generationJob->lead->uuid
            .'/results/'.$this->generationJob->group->uuid
            .'/'.$this->generationJob->period.'.'.$extension;
    }

    /**
     * Write a safe ordered debug step for this queued job.
     *
     * @param  array<string, mixed>  $context
     */
}
