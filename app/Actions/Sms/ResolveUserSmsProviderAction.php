<?php

namespace App\Actions\Sms;

use App\Enums\SmsProviderScope;
use App\Exceptions\Sms\SmsProviderNotAvailableException;
use App\Models\SmsProviderAccount;
use App\Models\User;

/**
 * Resolves the provider account a user should send SMS through.
 *
 * @since 1.2.0
 */
class ResolveUserSmsProviderAction
{
    /**
     * Resolve the active, accessible provider for the given user.
     *
     * @throws SmsProviderNotAvailableException
     */
    public function execute(User $user, string $purpose = 'general'): SmsProviderAccount
    {
        $settings = $user->smsSetting()->with('defaultProviderAccount')->first();
        $selected = $settings?->defaultProviderAccount;

        if ($selected?->isAccessibleBy($user)) {
            return $selected->loadMissing('type');
        }

        if ($settings && ! $settings->fallback_to_system) {
            throw new SmsProviderNotAvailableException('No accessible SMS provider is configured for this user.');
        }

        $systemProvider = $this->resolveSystemProvider($purpose);

        if (! $systemProvider) {
            throw new SmsProviderNotAvailableException('No active system SMS provider is available.');
        }

        return $systemProvider;
    }

    /**
     * Resolve an active system provider, preferring OTP pattern accounts for OTP.
     */
    private function resolveSystemProvider(string $purpose): ?SmsProviderAccount
    {
        $providers = SmsProviderAccount::query()
            ->with('type')
            ->where('scope', SmsProviderScope::System->value)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if (str_contains($purpose, 'otp')) {
            return $providers->first(fn (SmsProviderAccount $provider): bool => ($provider->settings['sending_type'] ?? null) === 'pattern')
                ?? $providers->first();
        }

        return $providers->first(fn (SmsProviderAccount $provider): bool => ($provider->settings['sending_type'] ?? null) !== 'pattern')
            ?? $providers->first();
    }
}
