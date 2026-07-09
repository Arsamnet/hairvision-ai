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

        try {
            $access = $selectedAccess
                ? $this->ensureSelectedAccessIsUsable($clinic, $selectedAccess, $usageCategoryKey, $scope)
                : $this->manager->resolveForClinic($clinic, $usageCategoryKey);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $exception;
        }

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

        try {
            $modelConfig = $this->resolver->model($access->model_key);

            $provider = $this->resolver->providerFor($access);

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

            $response = $provider->run($requestData);
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_code' => 'provider_exception',
                'error_message' => __('ai_models.errors.provider_failed'),
                'failed_at' => now(),
            ]);

            return new AiModelResponseData(
                successful: false,
                status: 'failed',
                errorCode: 'provider_exception',
                safeErrorMessage: __('ai_models.errors.provider_failed'),
                creditConsumed: 0,
            );
        }

        if (! $response->successful) {
            $log->update([
                'status' => 'failed',
                'response_reference' => $response->providerReference,
                'error_code' => $response->errorCode,
                'error_message' => $response->safeErrorMessage,
                'metadata_json' => $response->metadata,
                'failed_at' => now(),
            ]);

            return $response;
        }

        $creditConsumed = max(1, $response->creditConsumed);

        try {
            DB::transaction(function () use ($access, $log, $response, $creditConsumed): void {
                $lockedAccess = AiModelAccess::query()
                    ->lockForUpdate()
                    ->findOrFail($access->id);

                if (! $lockedAccess->hasAvailableCredit($creditConsumed)) {
                    throw ValidationException::withMessages([
                        'ai_model_access' => __('ai_models.errors.credit_finished'),
                    ]);
                }

                $lockedAccess->forceFill([
                    'credit_used' => $lockedAccess->credit_used + ($lockedAccess->hasUnlimitedCredit() ? 0 : $creditConsumed),
                    'usage_count' => $lockedAccess->usage_count + 1,
                    'last_used_at' => now(),
                ])->save();

                $log->update([
                    'status' => $response->status,
                    'credit_consumed' => $lockedAccess->hasUnlimitedCredit() ? 0 : $creditConsumed,
                    'response_reference' => $response->providerReference,
                    'metadata_json' => $response->metadata,
                    'completed_at' => now(),
                ]);

            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $exception;
        }

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
