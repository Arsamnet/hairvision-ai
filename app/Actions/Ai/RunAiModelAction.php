<?php

namespace App\Actions\Ai;

use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Models\AiModelAccess;
use App\Models\AiModelUsageLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiModelManager;
use App\Services\AI\AiModelResolver;
use App\Support\DebugStepLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Executes an assigned AI model access record.
 *
 * @since 1.0.0
 */
class RunAiModelAction
{
    private int $debugStepCounter = 0;

    public function __construct(
        private readonly AiModelManager $manager,
        private readonly AiModelResolver $resolver,
    ) {}

    /**
     * Execute an AI model and record usage.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function execute(
        Tenant $clinic,
        string $usageCategoryKey,
        array $payload = [],
        ?User $user = null,
        ?AiModelAccess $selectedAccess = null,
    ): AiModelResponseData {
        $scope = 'ai.model.run';
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Request received', [
            'clinic_id' => $clinic->id,
            'usage_category_key' => $usageCategoryKey,
            'user_id' => $user?->id,
            'has_selected_access' => $selectedAccess !== null,
            'selected_access_id' => $selectedAccess?->id,
            'payload_keys' => array_keys($payload),
            'payload_generation_job_id' => $payload['generation_job_id'] ?? null,
            'payload_upload_id' => $payload['upload_id'] ?? null,
            'payload_period' => $payload['period'] ?? null,
        ]);

        try {
            $access = $selectedAccess
                ? $this->ensureSelectedAccessIsUsable($clinic, $selectedAccess, $usageCategoryKey, $scope)
                : $this->manager->resolveForClinic($clinic, $usageCategoryKey);
        } catch (ValidationException $exception) {
            $this->debugStep($scope, 'AI model access validation failed', [
                'clinic_id' => $clinic->id,
                'usage_category_key' => $usageCategoryKey,
                'selected_access_id' => $selectedAccess?->id,
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'AI model access resolution failed', [
                'clinic_id' => $clinic->id,
                'usage_category_key' => $usageCategoryKey,
                'selected_access_id' => $selectedAccess?->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $this->debugStep($scope, 'AI model access resolved', [
            'clinic_id' => $clinic->id,
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'usage_category_key' => $usageCategoryKey,
            'has_credentials' => ! empty($access->credentials ?? []),
            'settings_count' => count($access->settings ?? []),
            'has_prompt' => $access->prompt !== null && $access->prompt !== '',
        ]);

        $log = AiModelUsageLog::create([
            'clinic_id' => $clinic->id,
            'user_id' => $user?->id,
            'ai_model_access_id' => $access->id,
            'usage_category_key' => $usageCategoryKey,
            'status' => 'started',
            'request_reference' => (string) Str::uuid(),
            'metadata_json' => [
                'model_key' => $access->model_key,
            ],
            'started_at' => now(),
        ]);

        $this->debugStep($scope, 'AI usage log created', [
            'usage_log_id' => $log->id,
            'clinic_id' => $clinic->id,
            'ai_model_access_id' => $access->id,
            'status' => $log->status,
        ]);

        try {
            $modelConfig = $this->resolver->model($access->model_key);

            $this->debugStep($scope, 'Model config resolved', [
                'model_key' => $access->model_key,
                'config_keys' => array_keys($modelConfig),
            ]);

            $provider = $this->resolver->providerFor($access);

            $this->debugStep($scope, 'Provider resolved', [
                'model_key' => $access->model_key,
                'provider_class' => $provider::class,
            ]);

            $requestData = new AiModelRequestData(
                clinic: $clinic,
                access: $access,
                usageCategoryKey: $usageCategoryKey,
                modelConfig: $modelConfig,
                credentials: $access->credentials ?? [],
                settings: $access->settings ?? [],
                prompt: $access->prompt,
                payload: $payload,
                user: $user,
            );

            $this->debugStep($scope, 'Provider request prepared', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $access->id,
                'model_key' => $access->model_key,
                'usage_category_key' => $usageCategoryKey,
                'has_credentials' => ! empty($access->credentials ?? []),
                'settings_count' => count($access->settings ?? []),
                'has_prompt' => $access->prompt !== null && $access->prompt !== '',
                'payload_keys' => array_keys($payload),
            ]);

            $response = $provider->run($requestData);
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Provider execution exception', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $access->id,
                'model_key' => $access->model_key,
                'exception' => $exception::class,
            ]);

            $log->update([
                'status' => 'failed',
                'error_code' => 'provider_exception',
                'error_message' => __('ai_models.errors.provider_failed'),
                'failed_at' => now(),
            ]);

            $this->debugStep($scope, 'AI usage log marked as failed', [
                'usage_log_id' => $log->id,
                'error_code' => 'provider_exception',
            ]);

            return new AiModelResponseData(
                successful: false,
                status: 'failed',
                errorCode: 'provider_exception',
                safeErrorMessage: __('ai_models.errors.provider_failed'),
                creditConsumed: 0,
            );
        }

        $this->debugStep($scope, 'Provider response received', [
            'successful' => $response->successful,
            'status' => $response->status,
            'error_code' => $response->errorCode,
            'has_provider_reference' => $response->providerReference !== null,
            'credit_consumed' => $response->creditConsumed,
            'metadata_keys' => array_keys($response->metadata),
            'output_keys' => array_keys($response->output),
        ]);

        if (! $response->successful) {
            $log->update([
                'status' => 'failed',
                'response_reference' => $response->providerReference,
                'error_code' => $response->errorCode,
                'error_message' => $response->safeErrorMessage,
                'metadata_json' => $response->metadata,
                'failed_at' => now(),
            ]);

            $this->debugStep($scope, 'Provider response marked as failed', [
                'usage_log_id' => $log->id,
                'status' => $response->status,
                'error_code' => $response->errorCode,
                'has_safe_error_message' => $response->safeErrorMessage !== null,
            ]);

            return $response;
        }

        $creditConsumed = max(1, $response->creditConsumed);

        $this->debugStep($scope, 'Credit transaction started', [
            'clinic_id' => $clinic->id,
            'ai_model_access_id' => $access->id,
            'credit_consumed' => $creditConsumed,
        ]);

        try {
            DB::transaction(function () use ($access, $log, $response, $creditConsumed, $scope): void {
                $lockedAccess = AiModelAccess::query()
                    ->lockForUpdate()
                    ->findOrFail($access->id);

                $this->debugStep($scope, 'AI model access locked', [
                    'ai_model_access_id' => $lockedAccess->id,
                    'has_unlimited_credit' => $lockedAccess->hasUnlimitedCredit(),
                    'credit_consumed' => $creditConsumed,
                    'has_available_credit' => $lockedAccess->hasAvailableCredit($creditConsumed),
                ]);

                if (! $lockedAccess->hasAvailableCredit($creditConsumed)) {
                    $this->debugStep($scope, 'AI model access credit unavailable', [
                        'ai_model_access_id' => $lockedAccess->id,
                        'credit_consumed' => $creditConsumed,
                    ]);

                    throw ValidationException::withMessages([
                        'ai_model_access' => __('ai_models.errors.credit_finished'),
                    ]);
                }

                if (! $lockedAccess->hasUnlimitedCredit()) {
                    $lockedAccess->increment('credit_used', $creditConsumed, [
                        'last_used_at' => now(),
                    ]);

                    $this->debugStep($scope, 'AI model access credit incremented', [
                        'ai_model_access_id' => $lockedAccess->id,
                        'credit_consumed' => $creditConsumed,
                    ]);
                } else {
                    $lockedAccess->forceFill(['last_used_at' => now()])->save();

                    $this->debugStep($scope, 'AI model access last used timestamp updated', [
                        'ai_model_access_id' => $lockedAccess->id,
                        'has_unlimited_credit' => true,
                    ]);
                }

                $log->update([
                    'status' => $response->status,
                    'credit_consumed' => $lockedAccess->hasUnlimitedCredit() ? 0 : $creditConsumed,
                    'response_reference' => $response->providerReference,
                    'metadata_json' => $response->metadata,
                    'completed_at' => now(),
                ]);

                $this->debugStep($scope, 'AI usage log marked as completed', [
                    'usage_log_id' => $log->id,
                    'status' => $response->status,
                    'credit_consumed' => $lockedAccess->hasUnlimitedCredit() ? 0 : $creditConsumed,
                ]);
            });
        } catch (ValidationException $exception) {
            $this->debugStep($scope, 'Credit transaction validation failed', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $access->id,
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Credit transaction exception', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $access->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $this->debugStep($scope, 'AI model run completed', [
            'clinic_id' => $clinic->id,
            'ai_model_access_id' => $access->id,
            'status' => $response->status,
            'credit_consumed' => $creditConsumed,
        ]);

        return $response;
    }

    /**
     * Ensure the selected model access can be used by the clinic.
     */
    private function ensureSelectedAccessIsUsable(
        Tenant $clinic,
        AiModelAccess $access,
        string $usageCategoryKey,
        string $scope
    ): AiModelAccess {
        $this->debugStep($scope, 'Validating selected AI model access', [
            'clinic_id' => $clinic->id,
            'selected_access_id' => $access->id,
            'model_key' => $access->model_key,
            'usage_category_key' => $usageCategoryKey,
        ]);

        $resolved = $this->manager->resolveForClinic($clinic, $usageCategoryKey, $access->id);

        if (! $resolved->is($access)) {
            $this->debugStep($scope, 'Selected AI model access mismatch', [
                'clinic_id' => $clinic->id,
                'selected_access_id' => $access->id,
                'resolved_access_id' => $resolved->id,
            ]);

            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.no_assigned_model'),
            ]);
        }

        $this->debugStep($scope, 'Selected AI model access validated', [
            'clinic_id' => $clinic->id,
            'selected_access_id' => $access->id,
        ]);

        return $resolved;
    }

    /**
     * Write a safe ordered debug step for this workflow.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}