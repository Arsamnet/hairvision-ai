<?php

namespace App\Services\Sms;

use App\Actions\Sms\ResolveUserSmsProviderAction;
use App\Contracts\Sms\SmsProviderFactoryInterface;
use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;
use App\Enums\SmsMessageStatus;
use App\Exceptions\Sms\SmsProviderException;
use App\Exceptions\Sms\SmsProviderNotAvailableException;
use App\Models\SmsDeliveryLog;
use App\Models\SmsProviderAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Central SMS sending service with provider resolution and safe logging.
 *
 * @since 1.2.0
 */
class SmsManager
{
    public function __construct(
        private readonly ResolveUserSmsProviderAction $resolver,
        private readonly SmsProviderFactoryInterface $providerFactory,
    ) {}

    /**
     * Resolve a provider for the user, send the SMS, and log the attempt.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws SmsProviderNotAvailableException
     */
    public function sendForUser(User $user, string $mobile, string $message, string $purpose = 'general', array $metadata = []): SmsSendResult
    {
        if (config('sms.fake.enabled')) {
            $result = SmsSendResult::sent('fake-'.Str::uuid(), metadata: ['fake' => true]);
            $this->logAttempt($user, null, $mobile, $message, $purpose, $result, $metadata);

            return $result;
        }

        $account = $this->resolver->execute($user, $purpose);

        try {
            $data = new SendSmsData($user, $mobile, $message, $purpose, $account, $metadata);
            $result = $this->providerFactory->make($account)->send($data);
        } catch (SmsProviderException $exception) {
            $result = SmsSendResult::failed('provider_error', $exception->getMessage());
        } catch (Throwable) {
            $result = SmsSendResult::failed('provider_error', 'SMS provider failed to send the message.');
        }

        $this->logAttempt($user, $account, $mobile, $message, $purpose, $result, $metadata);

        if ($result->status === SmsMessageStatus::Sent) {
            $account->forceFill(['last_used_at' => now()])->save();
        }

        return $result;
    }

    /**
     * Send an SMS through a system provider without a dashboard user owner.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws SmsProviderNotAvailableException
     */
    public function sendSystem(string $mobile, string $message, string $purpose = 'system', array $metadata = []): SmsSendResult
    {
        if (config('sms.fake.enabled')) {
            $result = SmsSendResult::sent('fake-'.Str::uuid(), metadata: ['fake' => true]);
            $this->logAttempt(null, null, $mobile, $message, $purpose, $result, $metadata);

            return $result;
        }

        $account = $this->resolveSystemProvider($purpose);

        try {
            $data = new SendSmsData(null, $mobile, $message, $purpose, $account, $metadata);
            $result = $this->providerFactory->make($account)->send($data);
        } catch (SmsProviderException $exception) {
            $result = SmsSendResult::failed('provider_error', $exception->getMessage());
        } catch (Throwable) {
            $result = SmsSendResult::failed('provider_error', 'SMS provider failed to send the message.');
        }

        $this->logAttempt(null, $account, $mobile, $message, $purpose, $result, $metadata);

        if ($result->status === SmsMessageStatus::Sent) {
            $account->forceFill(['last_used_at' => now()])->save();
        }

        return $result;
    }

    /**
     * Persist a sanitized delivery log.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logAttempt(
        ?User $user,
        ?SmsProviderAccount $account,
        string $mobile,
        string $message,
        string $purpose,
        SmsSendResult $result,
        array $metadata,
    ): SmsDeliveryLog {
        $messageHash = hash('sha256', $message);
        $preview = config('sms.logging.store_message_body')
            ? Str::limit($message, (int) config('sms.logging.preview_length'), '')
            : $this->safePreview($purpose, $message);

        return SmsDeliveryLog::create([
            'user_id' => $user?->id,
            'sms_provider_account_id' => $account?->id,
            'sms_provider_type_id' => $account?->sms_provider_type_id,
            'recipient_mobile' => $mobile,
            'message_preview' => $preview,
            'message_hash' => $messageHash,
            'purpose' => $purpose,
            'status' => $result->status,
            'provider_message_id' => $result->providerMessageId,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'cost' => $result->cost,
            'metadata' => array_merge($metadata, $result->metadata),
            'sent_at' => $result->status === SmsMessageStatus::Sent ? now() : null,
            'failed_at' => $result->status === SmsMessageStatus::Failed ? now() : null,
        ]);
    }

    /**
     * Build a safe message preview without storing OTP values.
     */
    private function safePreview(string $purpose, string $message): string
    {
        if (str_contains($purpose, 'otp')) {
            return 'OTP message';
        }

        return Str::limit(preg_replace('/[0-9۰-۹٠-٩]{4,}/u', '****', $message) ?? '', (int) config('sms.logging.preview_length'), '');
    }

    /**
     * Resolve the default active system provider.
     *
     * @throws SmsProviderNotAvailableException
     */
    private function resolveSystemProvider(string $purpose): SmsProviderAccount
    {
        $providers = SmsProviderAccount::query()
            ->with('type')
            ->where('scope', 'system')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $provider = str_contains($purpose, 'otp')
            ? $providers->first(fn (SmsProviderAccount $provider): bool => ($provider->settings['sending_type'] ?? null) === 'pattern')
            : $providers->first(fn (SmsProviderAccount $provider): bool => ($provider->settings['sending_type'] ?? null) !== 'pattern');

        $provider ??= $providers->first();

        if (! $provider) {
            throw new SmsProviderNotAvailableException('No active system SMS provider is available.');
        }

        return $provider;
    }
}
