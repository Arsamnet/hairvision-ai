<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\GeneratedAsset;
use App\Models\GenerationJob;
use App\Models\Upload;
use App\Services\AI\JpegImageNormalizer;
use App\Services\Billing\CreditLedgerService;
use App\Services\Generation\FinalGenerationSmsService;
use App\Services\Generation\GeneratedAssetIntegrityService;
use App\Services\Generation\GenerationGroupStatusService;
use App\Services\Generation\GenerationStageCatalog;
use App\Services\Generation\ProviderConcurrencyLimiter;
use App\Services\Generation\ProviderRetryPolicy;
use App\Support\SafeThrowableContext;
use App\Support\WorkerIdentity;
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
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
    public int $timeout;

    /**
     * Never repeat paid provider requests automatically.
     */
    public int $tries = 0;

    /**
     * Discard stale transports after an authorized lead deletion.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new process generation job instance.
     *
     * @param  GenerationJob  $generationJob  The generation job value.
     *
     * @since 1.0.0
     */
    public function __construct(public GenerationJob $generationJob)
    {
        $this->timeout = max(1, (int) config('hairvision.image_queue.worker_timeout', 1020) - 10);
        $this->afterCommit();
    }

    /**
     * Execute provider processing and persist the generated asset.
     *
     * @param  ProviderConcurrencyLimiter|null  $providerCapacity  Global provider capacity gate.
     * @param  GenerationGroupStatusService|null  $groupStatuses  Independent sibling status derivation.
     * @param  GeneratedAssetIntegrityService|null  $assetIntegrity  Existing private asset validator.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function handle(
        ?ProviderConcurrencyLimiter $providerCapacity = null,
        ?GenerationGroupStatusService $groupStatuses = null,
        ?GeneratedAssetIntegrityService $assetIntegrity = null,
    ): void {
        $scope = 'generation.job.process';
        $providerCapacity ??= app(ProviderConcurrencyLimiter::class);
        $groupStatuses ??= app(GenerationGroupStatusService::class);
        $assetIntegrity ??= app(GeneratedAssetIntegrityService::class);
        $providerLease = null;

        try {
            $this->generationJob->refresh();

            $existingAsset = $this->generationJob->asset()->first();

            if ($this->generationJob->status === 'completed'
                || ($existingAsset && $assetIntegrity->isValid($existingAsset))) {
                $this->generationJob->update([
                    'status' => 'completed',
                    'completed_at' => $this->generationJob->completed_at ?? now(),
                    'last_error' => null,
                    'error_code' => null,
                ]);

                $this->completeGroup($groupStatuses);

                return;
            }

            if (in_array($this->generationJob->status, ['failed', 'cancelled'], true)) {
                return;
            }

            $workerId = WorkerIdentity::current();
            $providerLease = $providerCapacity->acquire(
                'generation_job',
                (int) $this->generationJob->id,
                $workerId,
            );

            if (! $providerLease) {
                $delay = $providerCapacity->retryDelaySeconds();
                $this->generationJob->refresh();

                if (in_array($this->generationJob->status, ['pending', 'queued', 'retrying'], true)) {
                    $this->generationJob->forceFill([
                        'status' => 'queued',
                        'next_poll_at' => now()->addSeconds($delay),
                    ])->save();
                }

                $groupStatuses->refresh($this->generationJob->group);
                $this->release($delay);

                return;
            }

            $attempt = ((int) $this->generationJob->attempts) + 1;
            $queuedAt = $this->generationJob->queued_at ?? $this->generationJob->created_at;

            $this->generationJob->update([
                'status' => 'processing',
                'started_at' => now(),
                'attempts' => $attempt,
                'last_error' => null,
                'error_code' => null,
                'worker_id' => $workerId,
                'queue_wait_ms' => $queuedAt ? max(0, $queuedAt->diffInMilliseconds(now())) : null,
            ]);
            $groupStatuses->refresh($this->generationJob->group);

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
        } finally {
            $providerCapacity->release($providerLease);
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
            $providerStartedAt = now();
            $this->generationJob->forceFill([
                'provider_call_started_at' => $providerStartedAt,
            ])->save();

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

            $this->generationJob->forceFill([
                'provider_job_id' => $response->providerReference,
                'provider_call_finished_at' => now(),
                'provider_latency_ms' => max(0, $providerStartedAt->diffInMilliseconds(now())),
            ])->save();

            if (! $response->successful) {
                if ($response->retryable && $this->retryAfterDefiniteFailure($response)) {
                    return;
                }

                $this->markFailed($response->errorCode ?: 'provider_failed');

                return;
            }

            if (! $this->aiResponseHasImage($response)) {
                $this->markFailed('missing_generated_image');

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
            'error_code' => null,
        ]);

        $this->completeGroup(app(GenerationGroupStatusService::class));
    }

    /**
     * Complete the group or dispatch the next stage.
     *
     * @param  GenerationGroupStatusService  $groupStatuses  Independent sibling status derivation.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function completeGroup(GenerationGroupStatusService $groupStatuses): void
    {
        $scope = 'generation.job.process';

        $group = $this->generationJob->group()->with('jobs')->firstOrFail();
        $wasCompleted = $group->status === 'completed';
        $group = $groupStatuses->refresh($group);

        if (! $wasCompleted && $group->status === 'completed') {
            app(FinalGenerationSmsService::class)->sendCompleted($group);
        }
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
            'error_code' => $reasonCode,
            'provider_call_finished_at' => $this->generationJob->provider_call_finished_at ?? now(),
            'completed_at' => now(),
        ]);

        $this->logJobFailure($reasonCode, $exception);

        $group = $this->generationJob->group()->with('jobs')->first();

        if ($group) {
            app(GenerationGroupStatusService::class)->refresh($group);
        }

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
                'original_exception' => SafeThrowableContext::make($exception),
                'failure_exception' => SafeThrowableContext::make($failureException),
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
            'worker_id' => $this->generationJob->worker_id ?? WorkerIdentity::current(),
            'provider_request_id' => $this->generationJob->provider_job_id,
            'attempt' => $this->generationJob->attempts,
            'queue_wait_ms' => $this->generationJob->queue_wait_ms,
            'provider_latency_ms' => $this->generationJob->provider_latency_ms,
            'reason_code' => $reasonCode,
            'exception' => $exception ? SafeThrowableContext::make($exception) : null,
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
     * Requeue only a definite provider rejection or pre-request connection failure.
     *
     * @param  AiModelResponseData  $response  Provider response with retry metadata.
     * @return bool Whether a bounded retry was scheduled.
     *
     * @since 2.0.0
     */
    private function retryAfterDefiniteFailure(AiModelResponseData $response): bool
    {
        if ((int) $this->generationJob->attempts >= max(1, (int) $this->generationJob->max_attempts)) {
            return false;
        }

        $previousRetries = max(0, (int) $this->generationJob->attempts - 1);
        $decision = app(ProviderRetryPolicy::class)->decision($response, $previousRetries);

        if (! is_array($decision)) {
            return false;
        }

        $this->generationJob->forceFill([
            'status' => 'retrying',
            'next_poll_at' => now()->addSeconds($decision['delay_seconds']),
            'error_code' => $decision['reason'],
            'last_error' => null,
        ])->save();
        app(GenerationGroupStatusService::class)->refresh($this->generationJob->group);
        $this->release($decision['delay_seconds']);

        Log::warning('Generation job provider retry scheduled.', [
            'generation_job_id' => $this->generationJob->id,
            'generation_job_uuid' => $this->generationJob->uuid,
            'generation_group_id' => $this->generationJob->generation_group_id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'model_key' => $this->generationJob->aiModelAccess?->model_key,
            'provider' => $response->metadata['provider'] ?? null,
            'provider_request_id' => $response->metadata['request_id'] ?? null,
            'failure_stage' => $response->metadata['failure_stage'] ?? null,
            'curl_error_code' => $response->metadata['curl_error_code'] ?? null,
            'worker_id' => $this->generationJob->worker_id ?? WorkerIdentity::current(),
            'attempt' => $this->generationJob->attempts,
            'queue_wait_ms' => $this->generationJob->queue_wait_ms,
            'provider_latency_ms' => $this->generationJob->provider_latency_ms,
            'reason_code' => $decision['reason'],
            'retry_number' => $decision['retry_number'],
            'max_retries' => $decision['max_retries'],
            'delay_seconds' => $decision['delay_seconds'],
        ]);

        return true;
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
