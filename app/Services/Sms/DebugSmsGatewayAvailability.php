<?php

namespace App\Services\Sms;

use App\Models\SmsGateway;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the environment-only availability boundary for the debug SMS gateway.
 *
 * @since 2.1.0
 */
class DebugSmsGatewayAvailability
{
    public const PROVIDER_CODE = 'local';

    /**
     * Determine whether debug SMS delivery is explicitly allowed in this runtime.
     *
     * @return bool Whether debug SMS delivery may run.
     *
     * @since 2.1.0
     */
    public function isEnabled(): bool
    {
        $environment = strtolower(trim((string) config('app.env', 'production')));

        return $environment !== ''
            && ! in_array($environment, ['production', 'prod'], true)
            && (bool) config('app.debug', false)
            && (bool) config('hairvision.sms.debug_gateway.enabled', false);
    }

    /**
     * Determine whether a gateway record represents the debug/test provider.
     *
     * @param  SmsGateway  $gateway  SMS gateway being evaluated.
     * @return bool Whether the gateway is the debug/test gateway.
     *
     * @since 2.1.0
     */
    public function isDebugGateway(SmsGateway $gateway): bool
    {
        return $gateway->provider_code === self::PROVIDER_CODE;
    }

    /**
     * Return the runtime-effective status shown by management surfaces.
     *
     * @param  SmsGateway  $gateway  SMS gateway being evaluated.
     * @return string Stable SMS gateway status code.
     *
     * @since 2.1.0
     */
    public function effectiveStatus(SmsGateway $gateway): string
    {
        if ($this->isDebugGateway($gateway)) {
            return $this->isEnabled() ? 'enabled' : 'disabled';
        }

        return $gateway->status;
    }

    /**
     * Determine whether a gateway may be selected as its owner's default.
     *
     * Real providers are environment-independent; only the debug/test gateway
     * is constrained by the runtime safety switch.
     *
     * @param  SmsGateway  $gateway  SMS gateway being evaluated.
     * @return bool Whether the gateway may become the default.
     *
     * @since 2.1.0
     */
    public function canBeSelectedAsDefault(SmsGateway $gateway): bool
    {
        return ! $this->isDebugGateway($gateway) || $this->isEnabled();
    }

    /**
     * Reject selecting an unavailable debug/test gateway as default.
     *
     * @param  SmsGateway  $gateway  SMS gateway being selected.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 2.1.0
     */
    public function ensureCanBeSelectedAsDefault(SmsGateway $gateway): void
    {
        if ($this->canBeSelectedAsDefault($gateway)) {
            return;
        }

        throw ValidationException::withMessages([
            'sms_gateway' => __('sms.debug_gateway_unavailable'),
        ]);
    }

    /**
     * Reject every invocation of the local no-op adapter outside its safe runtime.
     *
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 2.1.0
     */
    public function ensureAdapterIsEnabled(): void
    {
        if ($this->isEnabled()) {
            return;
        }

        throw ValidationException::withMessages([
            'sms_gateway' => __('sms.debug_gateway_unavailable'),
        ]);
    }
}
