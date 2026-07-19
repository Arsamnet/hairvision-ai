<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\AiModelOutputConfig;
use App\Models\GeneratedAsset;
use App\Models\GenerationAttempt;
use App\Services\Billing\CreditLedgerService;
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

    public function __construct(public GenerationAttempt $attempt) {}

    public function handle(RunAiModelAction $runAiModel, CreditLedgerService $credits): void
    {
        try {
            $this->attempt->refresh()->load([
                'generationRequest.tenant',
                'generationRequest.lead',
                'generationRequest.sourceImage',
                'generationRequest.category',
                'generationRequest.output',
                'model.legacyAiModelAccess',
            ]);

            if ($this->attempt->status === 'completed') {
                return;
            }

            if ($this->attempt->status === 'cancelled' || $this->attempt->generationRequest?->status === 'cancelled') {
                return;
            }

            $request = $this->attempt->generationRequest;
            $model = $this->attempt->model;
            $source = $request?->sourceImage;
            $category = $request?->category;
            $output = $request?->output;
            $legacyAccess = $model?->legacy_ai_model_access_id ? $model->legacyAiModelAccess : null;

            if (! $request || ! $model || ! $source || ! $category || ! $output || ! $legacyAccess) {
                throw new RuntimeException('Independent generation attempt dependencies could not be resolved.');
            }

            $this->attempt->forceFill([
                'status' => 'processing',
                'provider_call_started_at' => now(),
                'safe_failure_class' => null,
                'safe_error_message' => null,
            ])->save();

            $request->forceFill([
                'status' => 'processing',
                'safe_error_code' => null,
                'safe_error_message' => null,
            ])->save();

            $outputConfig = $this->activeOutputConfig();

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
            );

            $this->attempt->forceFill([
                'provider_call_finished_at' => now(),
                'provider_reference' => $response->providerReference,
            ])->save();

            if (! $response->successful || ! $this->responseHasImage($response)) {
                $this->markFailed($response->errorCode ?: 'missing_generated_image', $response->safeErrorMessage);

                return;
            }

            $transaction = $credits->consume(
                tenant: $request->tenant,
                serviceKey: $category->code,
                amount: max(1, (int) $output->credit_cost),
                related: $request,
                description: __('credits.descriptions.ai_usage', ['period' => $output->name]),
            );

            $asset = $this->storeGeneratedAsset($response);

            DB::transaction(function () use ($request, $asset, $transaction): void {
                GeneratedAsset::query()
                    ->where('generation_request_id', $request->id)
                    ->whereKeyNot($asset->id)
                    ->where('is_current', true)
                    ->update([
                        'is_current' => false,
                        'archived_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->attempt->forceFill([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'safe_failure_class' => null,
                    'safe_error_message' => null,
                ])->save();

                $request->forceFill([
                    'current_generated_asset_id' => $asset->id,
                    'credit_transaction_id' => $transaction->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'failed_at' => null,
                    'safe_error_code' => null,
                    'safe_error_message' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            $this->markFailed('provider_exception', __('ai_models.errors.provider_failed'), $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed('queue_failed', __('ai_models.errors.provider_failed'), $exception);
    }

    private function activeOutputConfig(): ?AiModelOutputConfig
    {
        return AiModelOutputConfig::query()
            ->where('ai_model_id', $this->attempt->ai_model_id)
            ->where('ai_model_category_output_id', $this->attempt->generationRequest?->ai_model_category_output_id)
            ->where('is_active', true)
            ->first();
    }

    private function responseHasImage(AiModelResponseData $response): bool
    {
        return is_string($response->output['binary_content'] ?? null)
            && ($response->output['binary_content'] ?? '') !== '';
    }

    private function storeGeneratedAsset(AiModelResponseData $response): GeneratedAsset
    {
        $request = $this->attempt->generationRequest;

        if (! $request || ! $request->sourceImage || ! $request->category || ! $request->output) {
            throw new RuntimeException('Generation request was not loaded for asset storage.');
        }

        $extension = $this->safeExtension((string) ($response->output['extension'] ?? 'jpg'));
        $mime = (string) ($response->output['mime'] ?? 'image/jpeg');
        $safeMime = str_starts_with($mime, 'image/') ? $mime : 'image/jpeg';
        $binary = (string) $response->output['binary_content'];
        $destination = 'tenants/'.$request->tenant->uuid
            .'/leads/'.$request->lead->uuid
            .'/results/'.$request->uuid
            .'/'.$request->output->code.'-'.$this->attempt->attempt_number.'.'.$extension;

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
            'mime' => $safeMime,
            'size' => $disk->size($destination),
            'checksum' => hash('sha256', $binary),
            'provider_metadata_json' => $response->metadata,
        ]);
    }

    private function markFailed(string $code, ?string $message = null, ?Throwable $exception = null): void
    {
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

    private function safeExtension(string $extension): string
    {
        $extension = ltrim(strtolower($extension), '.');

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
