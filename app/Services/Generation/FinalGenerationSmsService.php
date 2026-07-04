<?php

namespace App\Services\Generation;

use App\Models\GenerationGroup;
use App\Support\DebugStepLogger;
use App\Services\Sms\SmsGatewayService;
use Throwable;

/**
 * Sends the customer notification after all generation stages complete.
 *
 * @since 1.0.0
 */
class FinalGenerationSmsService
{
    private int $debugStepCounter = 0;

    public function __construct(private readonly SmsGatewayService $smsGateways) {}

    public function sendCompleted(GenerationGroup $group): void
    {
        $scope = 'generation.final_sms';

        $this->debugStep($scope, 'Final generation SMS workflow started.', [
            'generation_group_id' => $group->id,
            'tenant_id' => $group->tenant_id,
            'lead_id' => $group->lead_id,
        ]);

        $lead = $group->lead;

        if (! $lead?->phone_normalized) {
            $this->debugStep($scope, 'Final generation SMS skipped because lead phone is missing.', [
                'generation_group_id' => $group->id,
                'tenant_id' => $group->tenant_id,
                'lead_id' => $group->lead_id,
                'lead_loaded' => $lead !== null,
            ]);

            return;
        }

        $message = __('ai.generation.sms.completed');

        $this->debugStep($scope, 'Final generation SMS message prepared.', [
            'generation_group_id' => $group->id,
            'tenant_id' => $group->tenant_id,
            'lead_id' => $group->lead_id,
            'phone_hash' => hash('sha256', $lead->phone_normalized),
            'message_length' => mb_strlen($message),
        ]);

        try {
            $this->smsGateways->sendSystemMessage(
                $lead->phone_normalized,
                $message
            );

            $this->debugStep($scope, 'Final generation SMS sent successfully.', [
                'generation_group_id' => $group->id,
                'tenant_id' => $group->tenant_id,
                'lead_id' => $group->lead_id,
                'phone_hash' => hash('sha256', $lead->phone_normalized),
            ]);
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Final generation SMS could not be sent.', [
                'generation_group_id' => $group->id,
                'tenant_id' => $group->tenant_id,
                'lead_id' => $group->lead_id,
                'phone_hash' => hash('sha256', $lead->phone_normalized),
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}