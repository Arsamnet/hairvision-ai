<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Upload;
use App\Services\AI\JpegImageNormalizer;
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

    /**
     * Create a new process generation job instance.
     *
     * @param  GenerationJob  $generationJob  The generation job value.
     *
     * @since 1.0.0
     */
    public function __construct(public GenerationJob $generationJob) {}

    /**
     * Execute provider processing and persist the generated asset.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
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
            if (! $upload instanceof Upload) {
                throw new RuntimeException('Generation upload could not be resolved.');
            }

            if (! $this->generationJob->aiModelAccess) {
                throw new RuntimeException('Assigned clinic AI model could not be resolved.');
            }

            $this->processWithAiModelAction($scope, $upload);
        } catch (Throwable $exception) {
            $this->safelyMarkFailedFromException($exception);

            throw $exception;
        }
    }

    /**
     * Laravel queue failure callback.
     *
     * @param  Throwable  $exception  Failure being handled.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function failed(Throwable $exception): void
    {
        $this->safelyMarkFailedFromException($exception);
    }

    /**
     * Process the stage through the configured AI model action.
     *
     * @param  string  $scope  The scope value.
     * @param  Upload  $upload  The upload value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
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
                lead: $upload->lead,
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

            try {
                $normalizedImage = app(JpegImageNormalizer::class)->normalizeWithProductDefaults(
                    (string) $response->output['binary_content'],
                );
            } catch (Throwable $exception) {
                $this->markFailed('missing_generated_image', $exception);

                return;
            }

            if (! $this->consumeServiceCredit()) {
                return;
            }

            $this->storeGeneratedBinaryAsset($normalizedImage, $response->metadata);
            $this->markCompletedAndContinue();
        } catch (Throwable $exception) {
            $this->markFailed('ai_model_exception', $exception);
        }
    }

    /**
     * Mark the stage as completed and continue the group.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @param  string  $reasonCode  The reason code value.
     * @param  Throwable|null  $exception  Failure being handled.
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @param  GenerationGroup  $group  The group value.
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
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
     *
     * @param  Throwable  $exception  Failure being handled.
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function publicFailureMessage(): string
    {
        return __('ai_models.errors.provider_failed');
    }

    /**
     * Log operational generation failures independently from debug tracing.
     *
     * @param  string  $reasonCode  The reason code value.
     * @param  Throwable|null  $exception  Failure being handled.
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
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
     *
     * @param  AiModelResponseData  $response  The response value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function aiResponseHasImage(AiModelResponseData $response): bool
    {
        return is_string($response->output['binary_content'] ?? null)
            && ($response->output['binary_content'] ?? '') !== '';
    }

    /**
     * Store normalized provider output into private result storage.
     *
     * @param  array{binary_content: string, mime: string, extension: string, size: int, width: int, height: int}  $normalized
     * @param  array<string, mixed>  $providerMetadata
     * @return void No value is returned.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    private function storeGeneratedBinaryAsset(array $normalized, array $providerMetadata): void
    {
        $scope = 'generation.job.process';

        $disk = Storage::disk('local');
        $destination = $this->destinationPath();

        $disk->makeDirectory(dirname($destination));
        $disk->put($destination, $normalized['binary_content']);

        $this->createAsset([
            'disk' => 'local',
            'path' => $destination,
            'mime' => $normalized['mime'],
            'size' => $normalized['size'],
            'checksum' => hash('sha256', $normalized['binary_content']),
            'provider_metadata_json' => $providerMetadata,
        ]);
    }

    /**
     * Create the generated asset database record.
     *
     * @param  array<string, mixed>  $attributes
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @return string Resolved string value.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    private function destinationPath(): string
    {
        return 'tenants/'.$this->generationJob->tenant->uuid
            .'/leads/'.$this->generationJob->lead->uuid
            .'/results/'.$this->generationJob->group->uuid
            .'/'.$this->generationJob->period.'.jpg';
    }

    /**
     * Write a safe ordered debug step for this queued job.
     *
     * @param  array<string, mixed>  $context
     */
}
