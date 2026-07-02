<?php

namespace App\Services\Generation;

use App\Models\GenerationGroup;
use App\Services\Sms\SmsGatewayService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the customer notification after all generation stages complete.
 *
 * @since 1.0.0
 */
class FinalGenerationSmsService
{
    public function __construct(private readonly SmsGatewayService $smsGateways) {}

    public function sendCompleted(GenerationGroup $group): void
    {
        $lead = $group->lead;

        if (! $lead?->phone_normalized) {
            return;
        }

        try {
            $this->smsGateways->sendSystemMessage(
                $lead->phone_normalized,
                __('ai.generation.sms.completed')
            );
        } catch (Throwable $exception) {
            Log::warning('Final generation SMS could not be sent.', [
                'generation_group_id' => $group->id,
                'tenant_id' => $group->tenant_id,
                'lead_id' => $group->lead_id,
                'error' => $exception::class,
            ]);
        }
    }
}
