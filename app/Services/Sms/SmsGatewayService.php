<?php

namespace App\Services\Sms;

use App\Models\SmsGateway;
use App\Models\User;
use App\Services\Sms\Adapters\IppanelSmsGatewayAdapter;
use App\Services\Sms\Adapters\LocalSmsGatewayAdapter;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use Illuminate\Validation\ValidationException;

/**
 * Resolves system and user-private SMS gateways and dispatches messages.
 *
 * @since 1.0.0
 */
class SmsGatewayService
{
    /**
     * Return the default system SMS gateway for platform messages.
     */
    public function systemDefault(): ?SmsGateway
    {
        return SmsGateway::query()
            ->system()
            ->enabled()
            ->where('is_default', true)
            ->first()
            ?? SmsGateway::query()->system()->enabled()->orderBy('id')->first();
    }

    /**
     * Return the user's private default gateway for their own customer messages.
     */
    public function userDefault(User $user): ?SmsGateway
    {
        return SmsGateway::query()
            ->where('owner_scope', 'user')
            ->where('user_id', $user->id)
            ->enabled()
            ->where('is_default', true)
            ->first()
            ?? SmsGateway::query()
                ->where('owner_scope', 'user')
                ->where('user_id', $user->id)
                ->enabled()
                ->orderBy('id')
                ->first();
    }

    /**
     * Send a platform/system message through the system default gateway.
     *
     * @throws ValidationException
     */
    public function sendSystemMessage(string $phoneNormalized, string $message): SmsGateway
    {
        $gateway = $this->systemDefault();

        if (! $gateway) {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_system_gateway'),
            ]);
        }

        $this->adapterFor($gateway)->send($gateway, $phoneNormalized, $message);

        return $gateway;
    }

    /**
     * Send a platform/system OTP through the system default gateway.
     *
     * @throws ValidationException
     */
    public function sendSystemOtp(string $phoneNormalized, string $code): SmsGateway
    {
        $gateway = $this->systemDefault();

        if (! $gateway) {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_system_gateway'),
            ]);
        }

        $this->adapterFor($gateway)->sendOtp($gateway, $phoneNormalized, $code);

        return $gateway;
    }

    /**
     * Send a user's customer message through their private gateway when active,
     * otherwise fall back to the system gateway.
     *
     * @throws ValidationException
     */
    public function sendUserCustomerMessage(User $user, string $phoneNormalized, string $message): SmsGateway
    {
        $gateway = $this->userDefault($user) ?? $this->systemDefault();

        if (! $gateway) {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_available_gateway'),
            ]);
        }

        $this->adapterFor($gateway)->send($gateway, $phoneNormalized, $message);

        return $gateway;
    }

    /**
     * Mark one system or user gateway as default and unset peers.
     */
    public function markDefault(SmsGateway $gateway): SmsGateway
    {
        SmsGateway::query()
            ->where('owner_scope', $gateway->owner_scope)
            ->when($gateway->owner_scope === 'user', fn ($query) => $query->where('user_id', $gateway->user_id))
            ->when($gateway->owner_scope === 'system', fn ($query) => $query->whereNull('user_id'))
            ->whereKeyNot($gateway->id)
            ->update(['is_default' => false]);

        $gateway->forceFill(['is_default' => true])->save();

        return $gateway->refresh();
    }

    /**
     * Resolve an adapter for the configured provider code.
     */
    private function adapterFor(SmsGateway $gateway): SmsGatewayAdapter
    {
        return match ($gateway->provider_code) {
            'ippanel' => app(IppanelSmsGatewayAdapter::class),
            'local', 'kavenegar', 'melipayamak' => app(LocalSmsGatewayAdapter::class),
            default => app(LocalSmsGatewayAdapter::class),
        };
    }
}
