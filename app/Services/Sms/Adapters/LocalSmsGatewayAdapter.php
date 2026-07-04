<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use App\Support\DebugStepLogger;

/**
 * Local no-op SMS adapter.
 *
 * @since 1.0.0
 */
class LocalSmsGatewayAdapter implements SmsGatewayAdapter
{
    /**
     * Pretend to send a normal SMS in local/test mode.
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void
    {
        $this->debugStep('sms.adapter.local.send', 'Local SMS send skipped', [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'phone_hash' => hash('sha256', $phoneNormalized),
            'message_length' => strlen($message),
        ]);
    }

    /**
     * Pretend to send an OTP SMS in local/test mode.
     */
    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void
    {
        $this->debugStep('sms.adapter.local.send_otp', 'Local OTP SMS send skipped', [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'phone_hash' => hash('sha256', $phoneNormalized),
            'code_length' => strlen($code),
        ]);
    }

    /**
     * Write a safe debug step for this adapter.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, 1, $message, $context);
    }
}