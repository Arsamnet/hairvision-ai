<?php

namespace App\Services\Sms;

use App\Models\SmsGateway;
use App\Models\User;
use App\Services\Sms\Adapters\IppanelSmsGatewayAdapter;
use App\Services\Sms\Adapters\LocalSmsGatewayAdapter;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use App\Support\DebugStepLogger;
use Illuminate\Validation\ValidationException;

/**
 * Resolves system and user-private SMS gateways and dispatches messages.
 *
 * @since 1.0.0
 */
class SmsGatewayService
{
    private ?string $debugSms = null;

    private int $debugStepCounter = 0;

    /**
     * Return the default system SMS gateway for platform messages.
     */
    public function systemDefault(): ?SmsGateway
    {
        $scope = 'sms.gateway.system_default';

        $this->debugStep($scope, 'System default SMS gateway resolution started.');

        $gateway = SmsGateway::query()
            ->system()
            ->enabled()
            ->where('is_default', true)
            ->first();

        if ($gateway instanceof SmsGateway) {
            $this->debugStep($scope, 'Default system SMS gateway found in database.', [
                'gateway' => $this->gatewayContext($gateway),
            ]);

            return $gateway;
        }

        $this->debugStep($scope, 'Default system SMS gateway was not found in database.');

        $gateway = SmsGateway::query()
            ->system()
            ->enabled()
            ->orderBy('id', 'asc')
            ->first();

        if ($gateway instanceof SmsGateway) {
            $this->debugStep($scope, 'Fallback enabled system SMS gateway found in database.', [
                'gateway' => $this->gatewayContext($gateway),
            ]);

            return $gateway;
        }

        $this->debugStep($scope, 'No enabled system SMS gateway found in database; checking config fallback.');

        $gateway = $this->systemDefaultFromConfig();

        $this->debugStep($scope, 'System default SMS gateway resolution completed.', [
            'resolved' => $gateway instanceof SmsGateway,
            'source' => $gateway instanceof SmsGateway ? 'config' : null,
            'gateway' => $gateway instanceof SmsGateway ? $this->gatewayContext($gateway) : null,
        ]);

        return $gateway;
    }

    /**
     * Return a system-level gateway built from config when no DB gateway exists.
     */
    private function systemDefaultFromConfig(): ?SmsGateway
    {
        $scope = 'sms.gateway.config_default';
        $provider = config('hairvision.sms.default_provider', 'ippanel');

        $this->debugStep($scope, 'Config SMS gateway resolution started.', [
            'provider_code' => $provider,
        ]);

        if ($provider === 'local') {
            $gateway = new SmsGateway([
                'name' => 'Local SMS Gateway (configured)',
                'provider_code' => 'local',
                'status' => 'enabled',
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'system_messages'],
            ]);

            $this->debugStep($scope, 'Local config SMS gateway resolved.', [
                'gateway' => $this->gatewayContext($gateway),
            ]);

            return $gateway;
        }

        $token = config('hairvision.sms.ippanel.authorization_token');

        if (! is_string($token) || trim($token) === '') {
            $this->debugStep($scope, 'IPPanel config SMS gateway could not be resolved because authorization token is missing.', [
                'provider_code' => 'ippanel',
                'has_authorization_token' => false,
            ]);

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

        $this->debugStep($scope, 'IPPanel config SMS gateway resolved.', [
            'gateway' => $this->gatewayContext($gateway),
            'has_authorization_token' => true,
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

        $this->debugStep($scope, 'System IPPanel SMS gateway resolution started.', [
            'default_provider' => $defaultProvider,
        ]);

        $gateways = SmsGateway::query()
            ->system()
            ->enabled()
            ->where('provider_code', 'ippanel')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $this->debugStep($scope, 'System IPPanel gateway candidates loaded.', [
            'candidate_count' => $gateways->count(),
        ]);

        $gateway = $gateways->first(function (SmsGateway $gateway): bool {
            $token = data_get($gateway->encrypted_credentials ?? [], 'api_key');

            return is_string($token) && trim($token) !== '';
        });

        if ($gateway instanceof SmsGateway) {
            $this->debugStep($scope, 'System IPPanel SMS gateway found in database.', [
                'gateway' => $this->gatewayContext($gateway),
            ]);

            return $gateway;
        }

        if ($defaultProvider === 'local') {
            $this->debugStep($scope, 'No database IPPanel gateway found; system IPPanel resolution redirected to local config provider.');

            return $this->systemDefaultFromConfig();
        }

        $this->debugStep($scope, 'No database IPPanel gateway with credentials found; checking config fallback.');

        $gateway = $this->systemDefaultFromConfig();

        $this->debugStep($scope, 'System IPPanel SMS gateway resolution completed.', [
            'resolved' => $gateway instanceof SmsGateway,
            'source' => $gateway instanceof SmsGateway ? 'config' : null,
            'gateway' => $gateway instanceof SmsGateway ? $this->gatewayContext($gateway) : null,
        ]);

        return $gateway;
    }

    /**
     * Return the user's private default gateway for their own customer messages.
     */
    public function userDefault(User $user): ?SmsGateway
    {
        $scope = 'sms.gateway.user_default';

        $this->debugStep($scope, 'User default SMS gateway resolution started.', [
            'user_id' => $user->id,
        ]);

        $gateway = SmsGateway::query()
            ->where('owner_scope', 'user')
            ->where('user_id', $user->id)
            ->enabled()
            ->where('is_default', true)
            ->first();

        if ($gateway instanceof SmsGateway) {
            $this->debugStep($scope, 'Default user SMS gateway found.', [
                'user_id' => $user->id,
                'gateway' => $this->gatewayContext($gateway),
            ]);

            return $gateway;
        }

        $this->debugStep($scope, 'Default user SMS gateway was not found; checking first enabled user gateway.', [
            'user_id' => $user->id,
        ]);

        $gateway = SmsGateway::query()
            ->where('owner_scope', 'user')
            ->where('user_id', $user->id)
            ->enabled()
            ->orderBy('id')
            ->first();

        $this->debugStep($scope, 'User SMS gateway resolution completed.', [
            'user_id' => $user->id,
            'resolved' => $gateway instanceof SmsGateway,
            'gateway' => $gateway instanceof SmsGateway ? $this->gatewayContext($gateway) : null,
        ]);

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

        $this->debugStep($scope, 'System SMS message send workflow started.', [
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'message_length' => mb_strlen($message),
        ]);

        $gateway = $this->systemDefault();

        if (! $gateway) {
            $this->debugStep($scope, 'System SMS message send failed because no system gateway is available.', [
                'phone_hash' => $this->phoneHash($phoneNormalized),
                'message_length' => mb_strlen($message),
            ]);

            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_system_gateway'),
            ]);
        }

        $this->debugStep($scope, 'System SMS gateway resolved for message send.', [
            'gateway' => $this->gatewayContext($gateway),
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'message_length' => mb_strlen($message),
        ]);

        $this->adapterFor($gateway)->send($gateway, $phoneNormalized, $message);

        $this->debugStep($scope, 'System SMS message sent successfully.', [
            'gateway' => $this->gatewayContext($gateway),
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'message_length' => mb_strlen($message),
        ]);

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

        $this->debugStep($scope, 'System OTP send workflow started.', [
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'code_length' => mb_strlen($code),
        ]);

        $gateway = $this->systemIppanel();

        if (! $gateway) {
            $this->debugStep($scope, 'System OTP send failed because no IPPanel gateway is available.', [
                'phone_hash' => $this->phoneHash($phoneNormalized),
                'code_length' => mb_strlen($code),
            ]);

            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_ippanel_gateway'),
            ]);
        }

        $this->debugStep($scope, 'System OTP gateway resolved.', [
            'gateway' => $this->gatewayContext($gateway),
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'code_length' => mb_strlen($code),
        ]);

        $this->adapterFor($gateway)->sendOtp($gateway, $phoneNormalized, $code);

        if ($gateway->provider_code === 'local') {
            $this->debugSms = $code;

            $this->debugStep($scope, 'Local OTP debug value stored without logging the OTP.', [
                'gateway' => $this->gatewayContext($gateway),
                'phone_hash' => $this->phoneHash($phoneNormalized),
                'code_length' => mb_strlen($code),
            ]);
        }

        $this->debugStep($scope, 'System OTP sent successfully.', [
            'gateway' => $this->gatewayContext($gateway),
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'code_length' => mb_strlen($code),
        ]);

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
        $this->resetDebugSteps();
        $this->debugSms = null;

        $scope = 'sms.user_customer_message';

        $this->debugStep($scope, 'User customer SMS message send workflow started.', [
            'user_id' => $user->id,
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'message_length' => mb_strlen($message),
        ]);

        $userGateway = $this->userDefault($user);
        $gateway = $userGateway ?? $this->systemDefault();

        if (! $gateway) {
            $this->debugStep($scope, 'User customer SMS message send failed because no gateway is available.', [
                'user_id' => $user->id,
                'phone_hash' => $this->phoneHash($phoneNormalized),
                'message_length' => mb_strlen($message),
                'user_gateway_resolved' => $userGateway instanceof SmsGateway,
            ]);

            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.no_available_gateway'),
            ]);
        }

        $this->debugStep($scope, 'SMS gateway resolved for user customer message.', [
            'user_id' => $user->id,
            'source' => $userGateway instanceof SmsGateway ? 'user' : 'system',
            'gateway' => $this->gatewayContext($gateway),
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'message_length' => mb_strlen($message),
        ]);

        $this->adapterFor($gateway)->send($gateway, $phoneNormalized, $message);

        $this->debugStep($scope, 'User customer SMS message sent successfully.', [
            'user_id' => $user->id,
            'source' => $userGateway instanceof SmsGateway ? 'user' : 'system',
            'gateway' => $this->gatewayContext($gateway),
            'phone_hash' => $this->phoneHash($phoneNormalized),
            'message_length' => mb_strlen($message),
        ]);

        return $gateway;
    }

    /**
     * Mark one system or user gateway as default and unset peers.
     */
    public function markDefault(SmsGateway $gateway): SmsGateway
    {
        $this->resetDebugSteps();

        $scope = 'sms.gateway.mark_default';

        $this->debugStep($scope, 'SMS gateway mark-default workflow started.', [
            'gateway' => $this->gatewayContext($gateway),
        ]);

        $updatedPeers = SmsGateway::query()
            ->where('owner_scope', $gateway->owner_scope)
            ->when($gateway->owner_scope === 'user', fn ($query) => $query->where('user_id', $gateway->user_id))
            ->when($gateway->owner_scope === 'tenant', fn ($query) => $query->where('tenant_id', $gateway->tenant_id))
            ->when($gateway->owner_scope === 'system', fn ($query) => $query->whereNull('user_id')->whereNull('tenant_id'))
            ->whereKeyNot($gateway->id)
            ->update(['is_default' => false]);

        $this->debugStep($scope, 'Peer SMS gateways unset as default.', [
            'gateway_id' => $gateway->id,
            'owner_scope' => $gateway->owner_scope,
            'user_id' => $gateway->user_id,
            'updated_peer_count' => $updatedPeers,
        ]);

        $gateway->forceFill(['is_default' => true])->save();
        $gateway->refresh();

        $this->debugStep($scope, 'SMS gateway marked as default successfully.', [
            'gateway' => $this->gatewayContext($gateway),
        ]);

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

        $this->debugStep('sms.gateway.adapter', 'SMS gateway adapter resolved.', [
            'gateway_id' => $gateway->id,
            'provider_code' => $gateway->provider_code,
            'adapter_class' => $adapter::class,
        ]);

        return $adapter;
    }

    private function resetDebugSteps(): void
    {
        $this->debugStepCounter = 0;
    }

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }

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
