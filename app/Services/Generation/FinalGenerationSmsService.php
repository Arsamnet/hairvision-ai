<?php

namespace App\Services\Generation;

use App\Models\GenerationGroup;
use App\Services\Sms\SmsGatewayService;
use Throwable;

/**
 * Sends the customer notification after all generation stages complete.
 *
 * @since 1.0.0
 */
class FinalGenerationSmsService
{
    /**
     * Create a new final generation sms service instance.
     *
     * @param  SmsGatewayService  $smsGateways  The sms gateways value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly SmsGatewayService $smsGateways) {}

    /**
     * Send completed.
     *
     * @param  GenerationGroup  $group  The group value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function sendCompleted(GenerationGroup $group): void
    {
        $scope = 'generation.final_sms';

        $lead = $group->lead;

        if (! $lead?->phone_normalized) {
            return;
        }

        $message = __('ai.generation.sms.completed');

        try {
            $this->smsGateways->sendSystemMessage(
                $lead->phone_normalized,
                $message
            );

        } catch (Throwable $exception) {
        }
    }
}
