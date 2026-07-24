<?php

namespace App\Actions\Ai;

use App\Models\AiModelAccess;
use App\Models\ClinicAiModelAccess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Assign or reactivate an AI model access record for a clinic.
 *
 * @since 1.0.0
 */
class AssignAiModelToClinicAction
{
    /**
     * Execute the assign ai model to clinic action operation.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  AiModelAccess  $access  The access value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @param  bool  $isActive  The is active value.
     * @return ClinicAiModelAccess Resolved operation result.
     *
     * @since 1.0.0
     */
    public function execute(Tenant $clinic, AiModelAccess $access, ?User $actor = null, bool $isActive = true): ClinicAiModelAccess
    {
        $this->resetDebugSteps();

        $scope = 'ai.assign_model_to_clinic';

        try {
            $assignment = DB::transaction(function () use ($clinic, $access, $actor, $isActive): ClinicAiModelAccess {
                $attributes = [
                    'is_active' => $isActive,
                ];

                if ($actor) {
                    $attributes['assigned_by'] = $actor->id;
                }

                $assignment = ClinicAiModelAccess::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'ai_model_access_id' => $access->id,
                    ],
                    $attributes
                );

                return $assignment;
            });

            return $assignment;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Execute the reset debug steps operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function resetDebugSteps(): void {}
}
