<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\GeneratedAsset;
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
use App\Support\DebugStepLogger;
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
    public int $timeout = 300;

    /**
     * Avoid duplicate credit consumption and duplicate provider calls.
     */
    public int $tries = 1;

    private int $debugStepCounter = 0;

    public function __construct(public GenerationJob $generationJob) {}

    /**
     * Execute provider processing and persist the generated asset.
     */
    public function handle(): void
    {
        $scope = 'generation.job.process';

        try {
            $this->debugStep($scope, 'Job started', [
                'period' => $this->generationJob->period,
                'model_key' => $this->generationJob->model_name,
            ]);

            $this->generationJob->refresh();

            $this->debugStep($scope, 'Generation job refreshed', [
                'status' => $this->generationJob->status,
                'has_asset' => $this->generationJob->asset()->exists(),
            ]);

            if ($this->generationJob->status === 'completed' || $this->generationJob->asset()->exists()) {
                $this->debugStep($scope, 'Job already completed or asset already exists', [
                    'status' => $this->generationJob->status,
                    'has_asset' => $this->generationJob->asset()->exists(),
                ]);

                $this->generationJob->update([
                    'status' => 'completed',
                    'completed_at' => $this->generationJob->completed_at ?? now(),
                    'last_error' => null,
                ]);

                $this->completeGroupOrDispatchNext();

                return;
            }

            $attempt = ((int) $this->generationJob->attempts) + 1;

            $this->generationJob->update([
                'status' => 'processing',
                'started_at' => now(),
                'attempts' => $attempt,
                'last_error' => null,
            ]);

            $this->debugStep($scope, 'Job marked as processing', [
                'attempt' => $attempt,
            ]);

            $upload = $this->generationJob->upload;
            $lead = $this->generationJob->lead;

            $this->debugStep($scope, 'Related upload and lead resolved', [
                'upload_id' => $upload?->id,
                'upload_uuid' => $upload?->uuid,
                'lead_id' => $lead?->id,
                'lead_uuid' => $lead?->uuid,
            ]);

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
            $this->debugStep($scope, 'Executing AI model action', [
                'ai_model_access_id' => $this->generationJob->aiModelAccess?->id,
                'model_key' => $this->generationJob->aiModelAccess?->model_key,
                'usage_category' => $this->generationJob->aiModelAccess?->usage_category_key,
                'has_prompt' => $this->generationJob->prompt !== null && $this->generationJob->prompt !== '',
                'prompt_length' => is_string($this->generationJob->prompt) ? strlen($this->generationJob->prompt) : 0,
            ]);

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

            $this->debugStep($scope, 'AI model action completed', [
                'successful' => $response->successful,
                'status' => $response->status,
                'error_code' => $response->errorCode,
            ]);

            if (! $response->successful) {
                $this->debugStep($scope, 'AI model action returned failure', [
                    'status' => $response->status,
                    'error_code' => $response->errorCode,
                    'has_safe_error_message' => $response->safeErrorMessage !== null,
                ]);

                $this->markFailed($response->safeErrorMessage ?: __('ai_models.errors.provider_failed'));

                return;
            }

            if (! $this->aiResponseHasImage($response)) {
                $this->debugStep($scope, 'AI model response missing generated image', [
                    'output_keys' => array_keys($response->output),
                ]);

                $this->markFailed(__('ai_models.errors.missing_generated_image'));

                return;
            }

            $this->debugStep($scope, 'Generated image response received', [
                'has_binary_content' => true,
                'mime' => $response->output['mime'] ?? null,
                'extension' => $response->output['extension'] ?? null,
            ]);

            if (! $this->consumeServiceCredit()) {
                return;
            }

            $this->storeGeneratedBinaryAsset($response);
            $this->markCompletedAndContinue();
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'AI model action exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->markFailed($this->safeFailureMessage($exception));
        }
    }

    /**
     * Process the stage through the legacy provider path.
     */
    private function processWithLegacyProvider(string $scope, Upload $upload, Lead $lead): void
    {
        try {
            $resolver = new ProviderResolver();
            $provider = $resolver->resolveForTenant($this->generationJob->tenant_id);

            $this->debugStep($scope, 'Legacy provider resolved', [
                'provider_class' => $provider::class,
            ]);

            $request = new ImageGenerationRequest(
                tenantUuid: $lead->tenant->uuid,
                leadUuid: $lead->uuid,
                uploadPath: $upload->original_path,
                period: $this->generationJob->period,
                prompt: $this->generationJob->prompt,
                modelName: $this->generationJob->model_name,
                settings: $this->generationJob->aiModelAccess?->settings ?? [],
            );

            $this->debugStep($scope, 'Legacy provider request prepared', [
                'period' => $this->generationJob->period,
                'model_name' => $this->generationJob->model_name,
                'has_prompt' => $this->generationJob->prompt !== null && $this->generationJob->prompt !== '',
                'settings_count' => count($this->generationJob->aiModelAccess?->settings ?? []),
            ]);

            $response = $provider->submit($request);

            $this->debugStep($scope, 'Legacy provider response received', [
                'response_status' => $response->status,
                'has_local_path' => ! empty($response->localPath),
            ]);

            if ($response->status !== 'completed' || ! $response->localPath) {
                $this->debugStep($scope, 'Legacy provider did not complete', [
                    'response_status' => $response->status,
                    'has_local_path' => ! empty($response->localPath),
                ]);

                $this->markFailed('sample provider did not return completed');

                return;
            }

            if (! $this->consumeServiceCredit()) {
                return;
            }

            $this->storeGeneratedLocalAsset($response);
            $this->markCompletedAndContinue();
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Legacy provider exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->markFailed($this->safeFailureMessage($exception));
        }
    }

    /**
     * Mark the stage as completed and continue the group.
     */
    private function markCompletedAndContinue(): void
    {
        $scope = 'generation.job.process';

        $this->generationJob->refresh();

        $this->debugStep($scope, 'Marking job as completed', [
            'period' => $this->generationJob->period,
        ]);

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

        $this->debugStep($scope, 'Checking group completion', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'group_status' => $group->status,
            'total_jobs' => $group->jobs->count(),
            'completed_jobs' => $completedCount,
            'failed_jobs' => $group->jobs->where('status', 'failed')->count(),
            'pending_jobs' => $group->jobs->where('status', 'pending')->count(),
            'processing_jobs' => $group->jobs->where('status', 'processing')->count(),
        ]);

        if ($group->jobs->contains(fn (GenerationJob $job): bool => $job->status === 'failed')) {
            $group->update(['status' => 'failed']);

            $this->debugStep($scope, 'Group marked as failed because at least one stage failed', [
                'generation_group_id' => $group->id,
                'generation_group_uuid' => $group->uuid,
            ]);

            return;
        }

        if ($group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed')) {
            $group->update(['status' => 'completed']);

            $this->debugStep($scope, 'Group marked as completed', [
                'generation_group_id' => $group->id,
                'generation_group_uuid' => $group->uuid,
                'was_completed' => $wasCompleted,
            ]);

            if (! $wasCompleted) {
                $this->debugStep($scope, 'Sending final generation SMS', [
                    'generation_group_id' => $group->id,
                    'generation_group_uuid' => $group->uuid,
                ]);

                app(FinalGenerationSmsService::class)->sendCompleted($group->refresh());

                $this->debugStep($scope, 'Final generation SMS dispatched', [
                    'generation_group_id' => $group->id,
                    'generation_group_uuid' => $group->uuid,
                ]);
            }

            return;
        }

        $group->update(['status' => 'processing']);

        $this->debugStep($scope, 'Group still processing; dispatching next stage', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'completed_jobs' => $completedCount,
            'total_jobs' => $group->jobs->count(),
        ]);

        app(GenerationService::class)->dispatchNextStage($group);

        $this->debugStep($scope, 'Next stage dispatch requested', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
        ]);
    }

    /**
     * Mark the stage and group as failed.
     */
    private function markFailed(string $message): void
    {
        $scope = 'generation.job.process';

        $this->generationJob->refresh();

        $this->debugStep($scope, 'Marking job as failed', [
            'period' => $this->generationJob->period,
            'attempt' => $this->generationJob->attempts,
            'has_error_message' => $message !== '',
        ]);

        $this->generationJob->update([
            'status' => 'failed',
            'last_error' => $message,
        ]);

        $group = $this->generationJob->group()->first();

        if ($group) {
            $group->update(['status' => 'failed']);
        }

        $this->debugStep($scope, 'Group marked as failed', [
            'generation_group_id' => $this->generationJob->generation_group_id,
        ]);
    }

    /**
     * Mark failed from an exception without leaving the job stuck in processing.
     */
    private function safelyMarkFailedFromException(Throwable $exception): void
    {
        try {
            $this->debugStep('generation.job.process', 'Unhandled generation job exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->markFailed($this->safeFailureMessage($exception));
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
     * Return a safe error message for DB/frontend storage.
     */
    private function safeFailureMessage(Throwable $exception): string
    {
        if (config('app.debug')) {
            return $exception->getMessage() ?: __('ai_models.errors.provider_failed');
        }

        return __('ai_models.errors.provider_failed');
    }

    /**
     * Consume one service credit before storing the generated output.
     */
    private function consumeServiceCredit(): bool
    {
        $scope = 'generation.job.process';
        $serviceKey = $this->generationJob->aiModelAccess?->usage_category_key ?? 'hair_transplant';

        try {
            $this->debugStep($scope, 'Consuming service credit', [
                'service_key' => $serviceKey,
            ]);

            app(CreditLedgerService::class)->consume(
                tenant: $this->generationJob->tenant,
                serviceKey: $serviceKey,
                related: $this->generationJob,
                description: __('credits.descriptions.ai_usage', [
                    'period' => app(GenerationStageCatalog::class)->label($this->generationJob->period),
                ]),
            );

            $this->debugStep($scope, 'Service credit consumed', [
                'service_key' => $serviceKey,
            ]);
        } catch (ValidationException $exception) {
            $error = collect($exception->errors())->flatten()->first() ?: __('credits.errors.consume_failed');

            $this->debugStep($scope, 'Service credit validation failed', [
                'service_key' => $serviceKey,
                'has_error' => $error !== '',
            ]);

            $this->markFailed($error);

            return false;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Service credit exception', [
                'service_key' => $serviceKey,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->markFailed(__('credits.errors.consume_failed'));

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

        $this->debugStep($scope, 'Storing local generated asset', [
            'source_path' => $sourcePath,
            'source_exists' => $source->exists($sourcePath),
            'extension' => 'jpg',
        ]);

        if (! $source->exists($sourcePath)) {
            throw new RuntimeException('Generated local provider file does not exist.');
        }

        $source->makeDirectory(dirname($destination));
        $source->copy($sourcePath, $destination);

        $size = $source->size($destination);
        $mime = is_string($response->metadata['mime'] ?? null)
            ? $response->metadata['mime']
            : 'image/jpeg';

        $this->debugStep($scope, 'Local generated asset stored', [
            'disk' => 'local',
            'path' => $destination,
            'mime' => $mime,
            'size' => $size,
        ]);

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

        $this->debugStep($scope, 'Storing binary generated asset', [
            'extension' => $extension,
            'mime' => $mime,
            'has_binary_content' => $this->aiResponseHasImage($response),
        ]);

        $disk->makeDirectory(dirname($destination));
        $disk->put($destination, $response->output['binary_content']);

        $size = $disk->size($destination);
        $safeMime = str_starts_with($mime, 'image/') ? $mime : 'image/jpeg';

        $this->debugStep($scope, 'Binary generated asset stored', [
            'disk' => 'local',
            'path' => $destination,
            'mime' => $safeMime,
            'size' => $size,
        ]);

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
            $this->debugStep($scope, 'Asset creation skipped because asset already exists');

            return;
        }

        $this->debugStep($scope, 'Creating generated asset record', [
            'disk' => $attributes['disk'] ?? null,
            'mime' => $attributes['mime'] ?? null,
            'size' => $attributes['size'] ?? null,
        ]);

        $asset = GeneratedAsset::create([
            'generation_group_id' => $this->generationJob->generation_group_id,
            'generation_job_id' => $this->generationJob->id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'period' => $this->generationJob->period,
        ] + $attributes);

        $this->debugStep($scope, 'Generated asset record created', [
            'asset_id' => $asset->id,
            'asset_uuid' => $asset->uuid,
        ]);
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
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, [
            'generation_job_id' => $this->generationJob->id,
            'generation_job_uuid' => $this->generationJob->uuid,
            'generation_group_id' => $this->generationJob->generation_group_id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
        ] + $context);
    }
}