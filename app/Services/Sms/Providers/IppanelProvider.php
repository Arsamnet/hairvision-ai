<?php

namespace App\Services\Sms\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;
use App\Exceptions\Sms\SmsProviderException;

/**
 * IPPanel SMS adapter for normal and OTP pattern sends.
 *
 * @since 1.2.0
 */
class IppanelProvider extends AbstractHttpSmsProvider implements SmsProviderInterface
{
    public function send(SendSmsData $data): SmsSendResult
    {
        $credentials = $this->requireCredentials(['api_key']);
        $settings = $this->account->settings ?? [];
        $sendingType = (string) ($settings['sending_type'] ?? 'normal');
        $fromNumber = $this->account->sender_number
            ?: ($settings['from_number'] ?? config('sms.providers.ippanel.from_number'));

        $payload = $sendingType === 'pattern'
            ? $this->patternPayload($data, $settings, $fromNumber)
            : $this->normalPayload($data, $fromNumber);

        $response = $this->http
            ->timeout((int) config('sms.http.timeout'))
            ->withHeaders([
                'Authorization' => $credentials['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->post((string) config('sms.providers.ippanel.endpoint'), $payload);

        return $this->resultFromResponse($response, 'data.message_id');
    }

    /**
     * Build IPPanel pattern payload for OTP verification messages.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws SmsProviderException
     */
    private function patternPayload(SendSmsData $data, array $settings, ?string $fromNumber): array
    {
        $verificationCode = $this->verificationCodeFrom($data->message);
        $patternCode = (string) ($settings['code'] ?? config('sms.providers.ippanel.otp_pattern_code'));

        if (blank($fromNumber) || blank($patternCode) || blank($verificationCode)) {
            throw new SmsProviderException('IPPanel OTP pattern settings are incomplete.');
        }

        return [
            'sending_type' => 'pattern',
            'from_number' => $fromNumber,
            'code' => $patternCode,
            'recipients' => [$this->normalizeRecipient($data->recipientMobile)],
            'params' => [
                'verification-code' => $verificationCode,
            ],
        ];
    }

    /**
     * Build IPPanel normal-send payload.
     */
    private function normalPayload(SendSmsData $data, ?string $fromNumber): array
    {
        return [
            'sending_type' => 'normal',
            'from_number' => $fromNumber,
            'recipients' => [$this->normalizeRecipient($data->recipientMobile)],
            'message' => $data->message,
        ];
    }

    /**
     * Extract a six-digit verification code from the rendered OTP message.
     */
    private function verificationCodeFrom(string $message): ?string
    {
        preg_match('/([0-9۰-۹٠-٩]{6})/u', $message, $matches);

        if (! isset($matches[1])) {
            return null;
        }

        return strtr($matches[1], [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * Convert Iranian local mobile numbers to IPPanel international format.
     */
    private function normalizeRecipient(string $mobile): string
    {
        $digits = preg_replace('/[^0-9]/', '', $mobile) ?? $mobile;

        if (str_starts_with($digits, '09')) {
            return '+98'.substr($digits, 1);
        }

        if (str_starts_with($digits, '98')) {
            return '+'.$digits;
        }

        return str_starts_with($mobile, '+') ? $mobile : '+'.$digits;
    }
}
