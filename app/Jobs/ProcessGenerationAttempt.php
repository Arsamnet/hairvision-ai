<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\AiModelOutputConfig;
use App\Models\GeneratedAsset;
use App\Models\GenerationAttempt;
use App\Services\AI\JpegImageNormalizer;
use App\Services\Billing\CreditLedgerService;
use App\Services\Generation\ModelQueueCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Processes one independent v2 generation attempt.
 *
 * @since 2.0.0
 */
class ProcessGenerationAttempt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesAndRestoresModelIdentifiers;
    use SerializesModels;

    /**
     * Keep paid image provider calls from being killed too early.
     */
    public int $timeout = 960;

    /**
     * Never repeat paid provider requests automatically.
     */
    public int $tries = 1;

    /**
     * Create a new process generation attempt instance.
     *
     * @param  GenerationAttempt  $attempt  The attempt value.
     *
     * @since 2.0.0
     */
    public function __construct(public GenerationAttempt $attempt) {}

    /**
     * Execute the process generation attempt operation.
     *
     * @param  RunAiModelAction  $runAiModel  The run ai model value.
     * @param  CreditLedgerService  $credits  The credits value.
     * @param  ModelQueueCoordinator  $queue  The queue value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function handle(
        RunAiModelAction $runAiModel,
        CreditLedgerService $credits,
        ModelQueueCoordinator $queue,
    ): void {
        $modelId = (int) $this->attempt->ai_model_id;

        try {
            $claimed = $queue->claimForProcessing($this->attempt);

            if (! $claimed) {
                return;
            }

            $this->attempt = $claimed;
            $this->attempt->refresh()->load([
                'generationRequest.tenant',
                'generationRequest.lead',
                'generationRequest.sourceImage',
                'generationRequest.category',
                'generationRequest.output',
                'model.legacyAiModelAccess',
            ]);

            $request = $this->attempt->generationRequest;
            $model = $this->attempt->model;
            $source = $request?->sourceImage;
            $category = $request?->category;
            $output = $request?->output;
            $legacyAccess = $model?->legacy_ai_model_access_id ? $model->legacyAiModelAccess : null;

            if (! $request || ! $model || ! $source || ! $category || ! $output || ! $legacyAccess) {
                throw new RuntimeException('Independent generation attempt dependencies could not be resolved.');
            }

            $outputConfig = $this->activeOutputConfig();

            $this->attempt->forceFill(['provider_call_started_at' => now()])->save();

            $response = $runAiModel->execute(
                clinic: $request->tenant,
                usageCategoryKey: $category->code,
                payload: [
                    'generation_request_id' => $request->uuid,
                    'generation_attempt_id' => $this->attempt->uuid,
                    'source_image_id' => $source->uuid,
                    'upload_disk' => $source->disk,
                    'upload_path' => $source->path,
                    'period' => $output->code,
                    'prompt' => $this->attempt->prompt,
                    'output_config' => $outputConfig?->configuration ?? [],
                ],
                selectedAccess: $legacyAccess,
                lead: $request->lead,
            );

            $this->attempt->forceFill([
                'provider_call_finished_at' => now(),
                'provider_reference' => $response->providerReference,
            ])->save();

            if (! $response->successful || ! $this->responseHasImage($response)) {
                if ($response->retryable && $this->scheduleRateLimitedRetry($response, $queue)) {
                    return;
                }

                $code = $response->errorCode ?: 'missing_generated_image';
                if (! $this->isDefiniteFailure($code)) {
                    $queue->recordProviderFailure($modelId);
                }
                $this->markFailed(
                    $code,
                    $response->safeErrorMessage,
                    credits: $credits,
                    releaseReservation: $this->isDefiniteFailure($code),
                );

                return;
            }

            try {
                $normalizedImage = app(JpegImageNormalizer::class)->normalizeWithProductDefaults(
                    (string) $response->output['binary_content'],
                );
            } catch (Throwable $exception) {
                $this->markFailed(
                    'missing_generated_image',
                    __('ai_models.errors.missing_generated_image'),
                    $exception,
                    $credits,
                    releaseReservation: true,
                );

                return;
            }

            $asset = $this->storeGeneratedAsset($response, $normalizedImage);
            $transaction = $credits->consumeReservation(
                attempt: $this->attempt,
                tenant: $request->tenant,
                serviceKey: $category->code,
                amount: max(1, (int) $output->credit_cost),
                description: __('credits.descriptions.ai_usage', ['period' => $output->name]),
            );

            DB::transaction(function () use ($request, $asset, $transaction): void {
                $sourceIsCurrent = (bool) $request->sourceImage()->lockForUpdate()->value('is_current');

                if ($sourceIsCurrent) {
                    GeneratedAsset::query()
                        ->where('generation_request_id', $request->id)
                        ->whereKeyNot($asset->id)
                        ->where('is_current', true)
                        ->update([
                            'is_current' => false,
                            'archived_at' => now(),
                            'updated_at' => now(),
                        ]);
                } else {
                    $asset->forceFill([
                        'is_current' => false,
                        'archived_at' => now(),
                    ])->save();
                }

                $this->attempt->forceFill([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'safe_failure_class' => null,
                    'safe_error_message' => null,
                ])->save();

                $request->forceFill([
                    'current_generated_asset_id' => $sourceIsCurrent ? $asset->id : null,
                    'credit_transaction_id' => $transaction->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'failed_at' => null,
                    'safe_error_code' => null,
                    'safe_error_message' => null,
                ])->save();
            });
            $queue->recordProviderSuccess($modelId);
        } catch (Throwable $exception) {
            $providerStarted = (bool) GenerationAttempt::query()
                ->whereKey($this->attempt->id)
                ->value('provider_call_started_at');
            $this->markFailed(
                'provider_exception',
                __('ai_models.errors.provider_failed'),
                $exception,
                $credits,
                releaseReservation: ! $providerStarted,
            );
            $queue->recordProviderFailure($modelId);
        } finally {
            if ($modelId > 0) {
                $queue->dispatchAvailableForModel($modelId);
            }
        }
    }

    /**
     * Execute the failed operation.
     *
     * @param  Throwable  $exception  Failure being handled.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function failed(Throwable $exception): void
    {
        $providerStarted = (bool) $this->attempt->provider_call_started_at;
        $this->markFailed(
            'queue_failed',
            __('ai_models.errors.provider_failed'),
            $exception,
            app(CreditLedgerService::class),
            releaseReservation: ! $providerStarted,
        );
        app(ModelQueueCoordinator::class)->dispatchAvailableForModel((int) $this->attempt->ai_model_id);
    }

    /**
     * Execute the active output config operation.
     *
     * @return AiModelOutputConfig|null Resolved operation result.
     *
     * @since 2.0.0
     */
    private function activeOutputConfig(): ?AiModelOutputConfig
    {
        return AiModelOutputConfig::query()
            ->where('ai_model_id', $this->attempt->ai_model_id)
            ->where('ai_model_category_output_id', $this->attempt->generationRequest?->ai_model_category_output_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Execute the response has image operation.
     *
     * @param  AiModelResponseData  $response  The response value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    private function responseHasImage(AiModelResponseData $response): bool
    {
        return is_string($response->output['binary_content'] ?? null)
            && ($response->output['binary_content'] ?? '') !== '';
    }

    /**
     * Execute the store generated asset operation.
     *
     * @param  array{binary_content: string, mime: string, extension: string, size: int, width: int, height: int}  $normalized
     * @param  AiModelResponseData  $response  The response value.
     * @return GeneratedAsset Resolved operation result.
     *
     * @since 2.0.0
     */
    private function storeGeneratedAsset(AiModelResponseData $response, array $normalized): GeneratedAsset
    {
        $request = $this->attempt->generationRequest;

        if (! $request || ! $request->sourceImage || ! $request->category || ! $request->output) {
            throw new RuntimeException('Generation request was not loaded for asset storage.');
        }

        $binary = $normalized['binary_content'];
        $destination = 'tenants/'.$request->tenant->uuid
            .'/leads/'.$request->lead->uuid
            .'/results/'.$request->uuid
            .'/'.$request->output->code.'-'.$this->attempt->attempt_number.'.jpg';

        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($destination));
        $disk->put($destination, $binary);

        $version = ((int) GeneratedAsset::query()
            ->where('generation_request_id', $request->id)
            ->max('version')) + 1;

        return GeneratedAsset::query()->create([
            'tenant_id' => $request->tenant_id,
            'lead_id' => $request->lead_id,
            'lead_source_image_id' => $request->lead_source_image_id,
            'ai_model_category_id' => $request->ai_model_category_id,
            'ai_model_category_output_id' => $request->ai_model_category_output_id,
            'generation_request_id' => $request->id,
            'generation_attempt_id' => $this->attempt->id,
            'period' => $request->output->code,
            'version' => $version,
            'is_current' => true,
            'disk' => 'local',
            'path' => $destination,
            'mime' => $normalized['mime'],
            'size' => $normalized['size'],
            'checksum' => hash('sha256', $binary),
            'provider_metadata_json' => $response->metadata,
        ]);
    }

    /**
     * Execute the mark failed operation.
     *
     * @param  string  $code  The code value.
     * @param  string|null  $message  The message value.
     * @param  Throwable|null  $exception  Failure being handled.
     * @param  CreditLedgerService|null  $credits  The credits value.
     * @param  bool  $releaseReservation  The release reservation value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function markFailed(
        string $code,
        ?string $message = null,
        ?Throwable $exception = null,
        ?CreditLedgerService $credits = null,
        bool $releaseReservation = false,
    ): void {
        $message = $message ?: __('ai_models.errors.provider_failed');

        try {
            $this->attempt->refresh();

            $this->attempt->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'provider_call_finished_at' => $this->attempt->provider_call_finished_at ?? now(),
                'safe_failure_class' => $code,
                'safe_error_message' => $message,
            ])->save();

            $this->attempt->generationRequest?->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'safe_error_code' => $code,
                'safe_error_message' => $message,
            ])->save();

            if ($releaseReservation && $credits) {
                $credits->releaseReservation($this->attempt, $message);
            }
        } catch (Throwable $failureException) {
            Log::error('Could not mark independent generation attempt as failed.', [
                'generation_attempt_id' => $this->attempt->id ?? null,
                'generation_attempt_uuid' => $this->attempt->uuid ?? null,
                'reason_code' => $code,
                'exception' => $exception ? $exception::class : null,
                'failure_exception' => $failureException::class,
            ]);
        }

        if ($exception) {
            Log::warning('Independent generation attempt failed.', [
                'generation_attempt_id' => $this->attempt->id ?? null,
                'generation_attempt_uuid' => $this->attempt->uuid ?? null,
                'generation_request_id' => $this->attempt->generation_request_id ?? null,
                'tenant_id' => $this->attempt->tenant_id ?? null,
                'lead_id' => $this->attempt->lead_id ?? null,
                'reason_code' => $code,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Execute the schedule rate limited retry operation.
     *
     * @param  AiModelResponseData  $response  The response value.
     * @param  ModelQueueCoordinator  $queue  The queue value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    private function scheduleRateLimitedRetry(AiModelResponseData $response, ModelQueueCoordinator $queue): bool
    {
        $retryAfter = max(5, min(
            (int) config('hairvision.generation.provider_rate_limit_max_delay_seconds', 300),
            $response->retryAfterSeconds ?? (int) config('hairvision.generation.provider_rate_limit_delay_seconds', 30),
        ));
        $maxRetries = max(0, (int) config('hairvision.generation.provider_rate_limit_retries', 3));

        $nextAttempt = DB::transaction(function () use ($maxRetries, $retryAfter): ?GenerationAttempt {
            $request = $this->attempt->generationRequest()->lockForUpdate()->first();

            if (! $request) {
                return null;
            }

            $rateLimitFailures = $request->attempts()
                ->where('safe_failure_class', 'provider_rate_limited')
                ->count();

            if ($rateLimitFailures >= $maxRetries) {
                return null;
            }

            $this->attempt->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'safe_failure_class' => 'provider_rate_limited',
                'safe_error_message' => __('ai_models.errors.provider_failed'),
            ])->save();

            $next = $request->attempts()->create([
                'tenant_id' => $this->attempt->tenant_id,
                'lead_id' => $this->attempt->lead_id,
                'ai_model_id' => $this->attempt->ai_model_id,
                'credit_transaction_id' => $this->attempt->credit_transaction_id,
                'attempt_number' => ((int) $request->attempts()->max('attempt_number')) + 1,
                'status' => 'queued',
                'available_at' => now()->addSeconds($retryAfter),
                'prompt' => $this->attempt->prompt,
                'safe_failure_class' => null,
                'safe_error_message' => null,
            ]);

            $request->forceFill([
                'status' => 'queued',
                'failed_at' => null,
                'safe_error_code' => null,
                'safe_error_message' => null,
            ])->save();

            return $next;
        });

        if (! $nextAttempt) {
            return false;
        }

        $queue->coolDownModel((int) $this->attempt->ai_model_id, $retryAfter);

        return true;
    }

    /**
     * Determine whether definite failure.
     *
     * @param  string  $code  The code value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    private function isDefiniteFailure(string $code): bool
    {
        return in_array($code, [
            'missing_credentials',
            'missing_upload',
            'sample_image_not_found',
            'demo_failed',
            'missing_generated_image',
            'provider_rate_limited',
            'provider_preflight_exception',
        ], true);
    }
}
