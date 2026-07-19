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
    public function execute(Tenant $clinic, AiModelAccess $access, User $actor, bool $isActive = true): ClinicAiModelAccess
    {
        $this->resetDebugSteps();

        $scope = 'ai.assign_model_to_clinic';

        try {
            $assignment = DB::transaction(function () use ($clinic, $access, $actor, $isActive): ClinicAiModelAccess {
                $assignment = ClinicAiModelAccess::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'ai_model_access_id' => $access->id,
                    ],
                    [
                        'is_active' => $isActive,
                        'assigned_by' => $actor->id,
                    ]
                );

                return $assignment;
            });

            return $assignment;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    private function resetDebugSteps(): void {}
}
