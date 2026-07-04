<?php

namespace App\Actions\Ai;

use App\Models\AiModelAccess;
use App\Models\ClinicAiModelAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DebugStepLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Assign or reactivate an AI model access record for a clinic.
 *
 * @since 1.0.0
 */
class AssignAiModelToClinicAction
{
    private int $debugStepCounter = 0;

    public function execute(Tenant $clinic, AiModelAccess $access, User $actor, bool $isActive = true): ClinicAiModelAccess
    {
        $this->resetDebugSteps();

        $scope = 'ai.assign_model_to_clinic';

        $this->debugStep($scope, 'Clinic AI model assignment workflow started.', [
            'clinic_id' => $clinic->id,
            'ai_model_access_id' => $access->id,
            'actor_id' => $actor->id,
            'is_active' => $isActive,
        ]);

        try {
            $assignment = DB::transaction(function () use ($clinic, $access, $actor, $isActive, $scope): ClinicAiModelAccess {
                $this->debugStep($scope, 'Clinic AI model access upsert started inside transaction.', [
                    'clinic_id' => $clinic->id,
                    'ai_model_access_id' => $access->id,
                    'actor_id' => $actor->id,
                    'is_active' => $isActive,
                ]);

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

                $this->debugStep($scope, 'Clinic AI model access record persisted.', [
                    'clinic_ai_model_access_id' => $assignment->id,
                    'clinic_id' => $assignment->clinic_id,
                    'ai_model_access_id' => $assignment->ai_model_access_id,
                    'assigned_by' => $assignment->assigned_by,
                    'is_active' => (bool) $assignment->is_active,
                    'was_recently_created' => $assignment->wasRecentlyCreated,
                ]);

                return $assignment;
            });

            $this->debugStep($scope, 'Clinic AI model assignment workflow completed successfully.', [
                'clinic_ai_model_access_id' => $assignment->id,
                'clinic_id' => $assignment->clinic_id,
                'ai_model_access_id' => $assignment->ai_model_access_id,
                'assigned_by' => $assignment->assigned_by,
                'is_active' => (bool) $assignment->is_active,
            ]);

            return $assignment;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Clinic AI model assignment workflow failed.', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $access->id,
                'actor_id' => $actor->id,
                'is_active' => $isActive,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function resetDebugSteps(): void
    {
        $this->debugStepCounter = 0;
    }

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}