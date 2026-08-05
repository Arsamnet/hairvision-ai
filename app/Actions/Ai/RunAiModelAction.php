<?php

namespace App\Actions\Ai;

use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Models\AiModelAccess;
use App\Models\AiModelUsageLog;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiModelManager;
use App\Services\AI\AiModelResolver;
use App\Services\AI\ApiImagePreprocessor;
use App\Services\AI\InputImageProcessingSettings;
use App\Support\SafeThrowableContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Executes an assigned AI model access record.
 *
 * @since 1.0.0
 */
class RunAiModelAction
{
    /**
     * Create a new run ai model action instance.
     *
     * @param  AiModelManager  $manager  The manager value.
     * @param  AiModelResolver  $resolver  The resolver value.
     * @param  ApiImagePreprocessor  $imagePreprocessor  The image preprocessor value.
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly AiModelManager $manager,
        private readonly AiModelResolver $resolver,
        private readonly ApiImagePreprocessor $imagePreprocessor,
    ) {}

    /**
     * Execute an AI model and record usage.
     *
     * @param  array<string, mixed>  $payload
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  User|null  $user  Authenticated user performing the operation.
     * @param  AiModelAccess|null  $selectedAccess  The selected access value.
     * @param  Lead|null  $lead  Lead in the current tenant scope.
     * @return AiModelResponseData HTTP response.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     * @since 2.1.1 Records successful model-credit usage even for unlimited provider access.
     * @since 2.1.2 Stores safe provider diagnostics for system model usage review.
     */
    public function execute(
        Tenant $clinic,
        string $usageCategoryKey,
        array $payload = [],
        ?User $user = null,
        ?AiModelAccess $selectedAccess = null,
        ?Lead $lead = null,
    ): AiModelResponseData {
        $scope = 'ai.model.run';

        $access = $selectedAccess
            ? $this->ensureSelectedAccessIsUsable($clinic, $selectedAccess, $usageCategoryKey, $scope)
            : $this->manager->resolveForClinic($clinic, $usageCategoryKey);
        $leadId = $lead && (int) $lead->tenant_id === (int) $clinic->id
            ? $lead->id
            : null;

        $log = AiModelUsageLog::create([
            'clinic_id' => $clinic->id,
            'lead_id' => $leadId,
            'user_id' => $user?->id,
            'ai_model_access_id' => $access->id,
            'usage_category_key' => $usageCategoryKey,
            'status' => 'started',
            'request_reference' => (string) Str::uuid(),
            'metadata_json' => $this->usageMetadata($access, $usageCategoryKey, $payload),
            'started_at' => now(),
        ]);

        $temporaryInput = null;
        $providerCreditReserved = false;
        $providerCallStarted = false;
        $providerStartedAt = null;
        $providerFinishedAt = null;
        $credentials = [];

        try {
            $providerCreditReserved = $this->reserveProviderCredit($access);
            $modelConfig = $this->resolver->model($access->model_key);
            $storedCredentials = $access->credentials;
            $credentials = is_array($storedCredentials) ? $storedCredentials : [];

            $provider = $this->resolver->providerFor($access);

            [$payload, $temporaryInput] = $this->prepareInputImage(
                $clinic,
                $access,
                $payload,
                (string) $log->request_reference,
            );

            $log->update([
                'metadata_json' => $this->usageMetadata($access, $usageCategoryKey, $payload),
            ]);

            $requestData = new AiModelRequestData(
                clinic: $clinic,
                access: $access,
                usageCategoryKey: $usageCategoryKey,
                modelConfig: $modelConfig,
                credentials: $credentials,
                settings: $access->settings ?? [],
                prompt: is_string($payload['prompt'] ?? null) ? $payload['prompt'] : null,
                payload: $payload,
                user: $user,
            );

            $providerStartedAt = now();
            $providerCallStarted = true;
            $response = $provider->run($requestData);
            $providerFinishedAt = now();
        } catch (Throwable $exception) {
            $providerFinishedAt = $providerCallStarted ? now() : null;
            $this->releaseProviderCredit($access, $providerCreditReserved);
            $errorCode = $providerCallStarted ? 'provider_exception' : 'provider_preflight_exception';
            $redactValues = array_values(array_filter([
                is_string($payload['prompt'] ?? null) ? $payload['prompt'] : null,
                is_string($payload['upload_path'] ?? null) ? $payload['upload_path'] : null,
                ...array_values(array_filter(
                    $credentials,
                    static fn (mixed $value): bool => is_string($value),
                )),
            ], static fn (mixed $value): bool => is_string($value) && $value !== ''));
            $exceptionContext = SafeThrowableContext::make($exception, $redactValues);

            Log::warning('AI model execution failed before a provider response was returned.', [
                'request_reference' => $log->request_reference,
                'tenant_id' => $clinic->id,
                'lead_id' => $leadId,
                'ai_model_access_id' => $access->id,
                'model_key' => $access->model_key,
                'usage_category' => $usageCategoryKey,
                'generation_request_uuid' => $payload['generation_request_id'] ?? null,
                'generation_attempt_uuid' => $payload['generation_attempt_id'] ?? null,
                'generation_job_uuid' => $payload['generation_job_id'] ?? null,
                'provider_call_started' => $providerCallStarted,
                'reason_code' => $errorCode,
                'exception' => $exceptionContext,
            ]);
            $log->update([
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => __('ai_models.errors.provider_failed'),
                'metadata_json' => $this->usageMetadata(
                    $access,
                    $usageCategoryKey,
                    $payload,
                    $providerStartedAt,
                    $providerFinishedAt,
                    exceptionContext: $exceptionContext,
                ),
                'failed_at' => now(),
            ]);

            return new AiModelResponseData(
                successful: false,
                status: 'failed',
                errorCode: $errorCode,
                safeErrorMessage: __('ai_models.errors.provider_failed'),
                creditConsumed: 0,
            );
        } finally {
            if (is_array($temporaryInput)) {
                Storage::disk($temporaryInput['disk'])->delete($temporaryInput['path']);
            }
        }

        if (! $response->successful) {
            $this->releaseProviderCredit($access, $providerCreditReserved);
            $log->update([
                'status' => 'failed',
                'response_reference' => $response->providerReference,
                'error_code' => $response->errorCode,
                'error_message' => $response->safeErrorMessage,
                'metadata_json' => $this->usageMetadata(
                    $access,
                    $usageCategoryKey,
                    $payload,
                    $providerStartedAt,
                    $providerFinishedAt,
                    $response,
                ),
                'failed_at' => now(),
            ]);

            return $response;
        }

        $creditConsumed = max(1, $response->creditConsumed);

        try {
            DB::transaction(function () use ($access, $log, $payload, $providerStartedAt, $providerFinishedAt, $response, $usageCategoryKey, $creditConsumed, $providerCreditReserved): void {
                $lockedAccess = AiModelAccess::query()
                    ->lockForUpdate()
                    ->findOrFail($access->id);

                $otherReservations = max(0, $lockedAccess->credit_reserved - ($providerCreditReserved ? 1 : 0));

                if (! $lockedAccess->hasUnlimitedCredit()
                    && $lockedAccess->credit_used + $otherReservations + $creditConsumed > $lockedAccess->credit_limit) {
                    throw ValidationException::withMessages([
                        'ai_model_access' => __('ai_models.errors.credit_finished'),
                    ]);
                }

                $lockedAccess->forceFill([
                    'credit_reserved' => $lockedAccess->hasUnlimitedCredit()
                        ? 0
                        : $otherReservations,
                    'credit_used' => $lockedAccess->credit_used + ($lockedAccess->hasUnlimitedCredit() ? 0 : $creditConsumed),
                    'usage_count' => $lockedAccess->usage_count + 1,
                    'last_used_at' => now(),
                ])->save();

                $log->update([
                    'status' => $response->status,
                    'credit_consumed' => $creditConsumed,
                    'response_reference' => $response->providerReference,
                    'metadata_json' => $this->usageMetadata(
                        $access,
                        $usageCategoryKey,
                        $payload,
                        $providerStartedAt,
                        $providerFinishedAt,
                        $response,
                    ),
                    'completed_at' => now(),
                ]);

            });
        } catch (ValidationException $exception) {
            $this->releaseProviderCredit($access, $providerCreditReserved);
            throw $exception;
        } catch (Throwable $exception) {
            $this->releaseProviderCredit($access, $providerCreditReserved);
            throw $exception;
        }

        return $response;
    }

    /**
     * Build safe operational metadata for model usage diagnostics.
     *
     * The payload summary intentionally omits prompts, credentials, raw provider
     * payloads, private paths, image bytes, cookies, and bearer tokens.
     *
     * @param  AiModelAccess  $access  Executed model access.
     * @param  string  $usageCategoryKey  Usage category key.
     * @param  array<string, mixed>  $payload  Provider payload before redaction.
     * @param  CarbonInterface|null  $providerStartedAt  Provider call start time.
     * @param  CarbonInterface|null  $providerFinishedAt  Provider call finish time.
     * @param  AiModelResponseData|null  $response  Normalized provider response.
     * @param  array<string, mixed>|null  $exceptionContext  Safe exception diagnostics.
     * @return array<string, mixed> Safe structured metadata.
     *
     * @since 2.1.2
     */
    private function usageMetadata(
        AiModelAccess $access,
        string $usageCategoryKey,
        array $payload,
        ?CarbonInterface $providerStartedAt = null,
        ?CarbonInterface $providerFinishedAt = null,
        ?AiModelResponseData $response = null,
        ?array $exceptionContext = null,
    ): array {
        $providerElapsedMs = $providerStartedAt && $providerFinishedAt
            ? max(0, $providerStartedAt->diffInMilliseconds($providerFinishedAt))
            : null;

        $metadata = [
            'model_key' => $access->model_key,
            'usage_category' => $usageCategoryKey,
            'request' => $this->safeRequestMetadata($payload),
            'timing' => array_filter([
                'provider_call_started_at' => $providerStartedAt?->toJSON(),
                'provider_call_finished_at' => $providerFinishedAt?->toJSON(),
                'provider_elapsed_ms' => $providerElapsedMs,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
        ];

        if ($response instanceof AiModelResponseData) {
            $metadata['provider_response'] = array_filter([
                'successful' => $response->successful,
                'status' => $response->status,
                'provider_reference' => $response->providerReference,
                'error_code' => $response->errorCode,
                'safe_error_message' => $response->safeErrorMessage,
                'credit_consumed' => $response->creditConsumed,
                'retryable' => $response->retryable,
                'retry_after_seconds' => $response->retryAfterSeconds,
                'metadata' => $response->metadata,
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }

        if (is_array($exceptionContext)) {
            $metadata['exception'] = $exceptionContext;
        }

        return array_filter($metadata, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Summarize only safe request metadata for dashboard diagnostics.
     *
     * @param  array<string, mixed>  $payload  Provider payload before redaction.
     * @return array<string, mixed> Safe request metadata.
     *
     * @since 2.1.2
     */
    private function safeRequestMetadata(array $payload): array
    {
        return array_filter([
            'generation_request_uuid' => $this->stringPayload($payload, 'generation_request_id'),
            'generation_attempt_uuid' => $this->stringPayload($payload, 'generation_attempt_id'),
            'generation_job_uuid' => $this->stringPayload($payload, 'generation_job_id'),
            'source_image_uuid' => $this->stringPayload($payload, 'source_image_id'),
            'output_key' => $this->stringPayload($payload, 'period'),
            'prompt_length' => is_string($payload['prompt'] ?? null) ? mb_strlen($payload['prompt']) : null,
            'input_image' => array_filter([
                'mime' => $this->stringPayload($payload, 'upload_mime'),
                'size' => is_numeric($payload['upload_size'] ?? null) ? (int) $payload['upload_size'] : null,
                'width' => is_numeric($payload['upload_width'] ?? null) ? (int) $payload['upload_width'] : null,
                'height' => is_numeric($payload['upload_height'] ?? null) ? (int) $payload['upload_height'] : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Return a safe scalar payload value.
     *
     * @param  array<string, mixed>  $payload  Provider payload before redaction.
     * @param  string  $key  Payload key.
     * @return string|null Safe string value.
     *
     * @since 2.1.2
     */
    private function stringPayload(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_scalar($value) && $value !== ''
            ? (string) $value
            : null;
    }

    /**
     * Reserve provider credit.
     *
     * @param  AiModelAccess  $access  The access value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    private function reserveProviderCredit(AiModelAccess $access): bool
    {
        return DB::transaction(function () use ($access): bool {
            $locked = AiModelAccess::query()->lockForUpdate()->findOrFail($access->id);

            if ($locked->hasUnlimitedCredit()) {
                return false;
            }

            if (! $locked->hasAvailableCredit()) {
                throw ValidationException::withMessages([
                    'ai_model_access' => __('ai_models.errors.credit_finished'),
                ]);
            }

            $locked->increment('credit_reserved');

            return true;
        });
    }

    /**
     * Release provider credit.
     *
     * @param  AiModelAccess  $access  The access value.
     * @param  bool  $reserved  The reserved value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function releaseProviderCredit(AiModelAccess $access, bool $reserved): void
    {
        if (! $reserved) {
            return;
        }

        DB::transaction(function () use ($access): void {
            $locked = AiModelAccess::query()->lockForUpdate()->find($access->id);

            if ($locked && $locked->credit_reserved > 0) {
                $locked->decrement('credit_reserved');
            }
        });
    }

    /**
     * Replace the provider payload image with a private processed JPEG when enabled.
     *
     * @param  array<string, mixed>  $payload
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  AiModelAccess  $access  The access value.
     * @param  string  $requestReference  The request reference value.
     * @return array{0: array<string, mixed>, 1: array{disk: string, path: string}|null}
     *
     * @since 1.0.0
     */
    private function prepareInputImage(
        Tenant $clinic,
        AiModelAccess $access,
        array $payload,
        string $requestReference,
    ): array {
        $settings = InputImageProcessingSettings::normalize($access->settings ?? []);

        if (! InputImageProcessingSettings::enabled($settings)) {
            return [$payload, null];
        }

        $sourceDisk = (string) data_get($payload, 'upload_disk', 'local');
        $sourcePath = (string) data_get($payload, 'upload_path');
        $maxDimension = $settings[InputImageProcessingSettings::MAX_DIMENSION] ?? null;
        $jpegQuality = $settings[InputImageProcessingSettings::JPEG_QUALITY] ?? null;

        if ($sourcePath === '' || ! is_int($maxDimension) || ! is_int($jpegQuality)) {
            throw new RuntimeException('Enabled input image processing settings are incomplete.');
        }

        $destination = 'ai-model-inputs/'.$clinic->uuid.'/'.$requestReference.'.jpg';
        $prepared = $this->imagePreprocessor->prepareForImageApi(
            sourceDisk: $sourceDisk,
            sourcePath: $sourcePath,
            destinationPath: $destination,
            maxDimension: $maxDimension,
            jpegQuality: $jpegQuality,
        );

        $payload['upload_disk'] = $prepared['disk'];
        $payload['upload_path'] = $prepared['path'];
        $payload['upload_mime'] = $prepared['mime'];
        $payload['upload_size'] = $prepared['size'];
        $payload['upload_width'] = $prepared['width'];
        $payload['upload_height'] = $prepared['height'];

        return [
            $payload,
            [
                'disk' => $prepared['disk'],
                'path' => $prepared['path'],
            ],
        ];
    }

    /**
     * Ensure the selected model access can be used by the clinic.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  AiModelAccess  $access  The access value.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  string  $scope  The scope value.
     * @return AiModelAccess Resolved operation result.
     *
     * @since 1.0.0
     */
    private function ensureSelectedAccessIsUsable(
        Tenant $clinic,
        AiModelAccess $access,
        string $usageCategoryKey,
        string $scope
    ): AiModelAccess {
        $resolved = $this->manager->resolveForClinic($clinic, $usageCategoryKey, $access->id);

        if (! $resolved->is($access)) {
            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.no_assigned_model'),
            ]);
        }

        return $resolved;
    }

    /**
     * Write a safe ordered debug step for this workflow.
     *
     * @param  array<string, mixed>  $context
     */
}
