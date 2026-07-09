<?php

namespace App\Services\Sms;

use App\Models\SmsGateway;
use App\Services\Sms\Adapters\IppanelSmsGatewayAdapter;
use App\Services\Sms\Adapters\LocalSmsGatewayAdapter;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use Illuminate\Validation\ValidationException;

/**
 * Resolves system SMS gateways and dispatches messages.
 *
 * @since 1.0.0
 */
class SmsGatewayService
{
    private ?string $debugSms = null;

    /**
     * Return the default system SMS gateway for platform messages.
     */
    public function systemDefault(): ?SmsGateway
    {
        $scope = 'sms.gateway.system_default';

        $gateway = SmsGateway::query()
            ->system()
            ->enabled()
            ->where('is_default', true)
            ->first();

        if ($gateway instanceof SmsGateway) {
            return $gateway;
        }

        $gateway = SmsGateway::query()
            ->system()
            ->enabled()
            ->orderBy('id', 'asc')
            ->first();

        if ($gateway instanceof SmsGateway) {
            return $gateway;
        }

        $gateway = $this->systemDefaultFromConfig();

        return $gateway;
    }

    /**
     * Return a system-level gateway built from config when no DB gateway exists.
     */
    private function systemDefaultFromConfig(): ?SmsGateway
    {
        $scope = 'sms.gateway.config_default';
        $provider = config('hairvision.sms.default_provider', 'ippanel');

        if ($provider === 'local') {
            $gateway = new SmsGateway([
                'name' => 'Local SMS Gateway (configured)',
                'provider_code' => 'local',
                'status' => 'enabled',
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'system_messages'],
            ]);

            return $gateway;
        }

        $token = config('hairvision.sms.ippanel.authorization_token');

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $gateway = new SmsGateway([
            'name' => 'IPPanel (configured)',
            'provider_code' => 'ippanel',
            'status' => 'enabled',
            'encrypted_credentials' => [
                'api_key' => trim($token),
            ],
            'settings_json' => [
                'from_number' => config('hairvision.sms.ippanel.from_number'),
                'endpoint_url' => config('hairvision.sms.ippanel.endpoint_url'),
                'otp_pattern_code' => config('hairvision.sms.ippanel.otp_pattern_code'),
                'otp_pattern_variable' => config('hairvision.sms.ippanel.otp_pattern_variable'),
                'timeout_seconds' => config('hairvision.sms.ippanel.timeout_seconds'),
                'connect_timeout_seconds' => config('hairvision.sms.ippanel.connect_timeout_seconds'),
            ],
        ]);

        return $gateway;
    }

    /**
     * Return the enabled system IPPanel gateway used for all OTP messages.
     */
    public function systemIppanel(): ?SmsGateway
    {
        $scope = 'sms.gateway.system_ippanel';
        $defaultProvider = config('hairvision.sms.default_provider', 'ippanel');

        $gateways = SmsGateway::query()
            ->system()
            ->enabled()
            ->where('provider_code', 'ippanel')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $gateway = $gateways->first(function (SmsGateway $gateway): bool {
            $token = data_get($gateway->encrypted_credentials ?? [], 'api_key');

            return is_string($token) && trim($token) !== '';
        });

        if ($gateway instanceof SmsGateway) {
            return $gateway;
        }

        if ($defaultProvider === 'local') {
            return $this->systemDefaultFromConfig();
        }

        $gateway = $this->systemDefaultFromConfig();

        return $gateway;
    }

    /**
     * Send a platform/system message through the system default gateway.
     *
     * @throws ValidationException
     */
    public function sendSystemMessage(string $phoneNormalized, string $message): SmsGateway
    {
        $this->resetDebugSteps();
        $this->debugSms = null;

        $scope = 'sms.system_message';

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
     * Send a platform/system OTP through the system IPPanel gateway.
     *
     * @throws ValidationException
     */
    public function sendSystemOtp(string $phoneNormalized, string $code): SmsGateway
    {
        $this->resetDebugSteps();
        $this->debugSms = null;

        $scope = 'sms.system_otp';

        $gateway = $this->systemIppanel();

        if (! $gateway) {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_ippanel_gateway'),
            ]);
        }

        $this->adapterFor($gateway)->sendOtp($gateway, $phoneNormalized, $code);

        if ($gateway->provider_code === 'local') {
            $this->debugSms = $code;

        }

        return $gateway;
    }

    /**
     * Mark one system or user gateway as default and unset peers.
     */
    public function markDefault(SmsGateway $gateway): SmsGateway
    {
        $this->resetDebugSteps();

        $scope = 'sms.gateway.mark_default';

        $updatedPeers = SmsGateway::query()
            ->where('owner_scope', $gateway->owner_scope)
            ->when($gateway->owner_scope === 'user', fn ($query) => $query->where('user_id', $gateway->user_id))
            ->when($gateway->owner_scope === 'tenant', fn ($query) => $query->where('tenant_id', $gateway->tenant_id))
            ->when($gateway->owner_scope === 'system', fn ($query) => $query->whereNull('user_id')->whereNull('tenant_id'))
            ->whereKeyNot($gateway->id)
            ->update(['is_default' => false]);

        $gateway->forceFill(['is_default' => true])->save();
        $gateway->refresh();

        return $gateway;
    }

    public function lastDebugSms(): ?string
    {
        return $this->debugSms;
    }

    /**
     * Resolve an adapter for the configured provider code.
     */
    private function adapterFor(SmsGateway $gateway): SmsGatewayAdapter
    {
        $adapter = match ($gateway->provider_code) {
            'ippanel' => app(IppanelSmsGatewayAdapter::class),
            'local', 'kavenegar', 'melipayamak' => app(LocalSmsGatewayAdapter::class),
            default => app(LocalSmsGatewayAdapter::class),
        };

        return $adapter;
    }

    private function resetDebugSteps(): void {}

    private function gatewayContext(SmsGateway $gateway): array
    {
        $settings = $gateway->settings_json ?? [];
        $credentials = $gateway->encrypted_credentials ?? [];

        return [
            'id' => $gateway->id,
            'owner_scope' => $gateway->owner_scope,
            'user_id' => $gateway->user_id,
            'provider_code' => $gateway->provider_code,
            'status' => $gateway->status,
            'is_default' => (bool) $gateway->is_default,
            'exists' => $gateway->exists,
            'has_credentials' => count($credentials) > 0,
            'credential_keys' => array_keys($credentials),
            'settings_keys' => array_keys($settings),
            'endpoint_host' => $this->endpointHost(data_get($settings, 'endpoint_url')),
        ];
    }

    private function phoneHash(string $phoneNormalized): string
    {
        return hash('sha256', $phoneNormalized);
    }

    private function endpointHost(mixed $endpointUrl): ?string
    {
        if (! is_string($endpointUrl) || trim($endpointUrl) === '') {
            return null;
        }

        $host = parse_url($endpointUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
