<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\OtpCode;
use App\Services\Sms\SmsGatewayService;
use App\Support\NumberNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Random\RandomException;

/**
 * Manages tenant-scoped OTP creation, normalization, and verification.
 *
 * OTP codes are stored only as hashes. Debug codes are attached to the returned
 * model instance only when Laravel debug mode is enabled.
 *
 * @since 1.0.0
 */
class OtpService
{
    public function __construct(private readonly SmsGatewayService $smsGateways) {}

    /**
     * Create a hashed OTP record for a normalized visitor phone number.
     *
     * @param  string  $phone  Visitor phone number in a supported digit script.
     * @param  string  $ipAddress  Request IP address stored for abuse review.
     * @param  int  $tenantId  Tenant id resolved from the request host.
     * @return OtpCode Created OTP model; debug mode may add a transient `debug_code` attribute.
     *
     * @throws RandomException When secure OTP generation fails.
     */
    public function requestCode(string $phone, string $ipAddress, int $tenantId): OtpCode
    {
        $phoneNormalized = $this->normalizePhone($phone);
        $expiresAt = Carbon::now()->addMinutes(2);
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'tenant_id' => $tenantId,
            'phone_normalized' => $phoneNormalized,
            'code_hash' => Hash::make($code),
            'purpose' => 'lead_login',
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
            'attempts' => 0,
        ]);

        if (app()->hasDebugModeEnabled()) {
            $otp->debug_code = $code;
        }

        if ($this->smsGateways->systemDefault()) {
            $this->smsGateways->sendSystemOtp($phoneNormalized, $code);
        }

        return $otp;
    }

    /**
     * Verify an OTP and create or update the tenant-scoped verified lead.
     *
     * Side effects: increments failed attempts, marks OTP as verified, and
     * upserts the lead record.
     *
     * @param  string  $phone  Visitor phone number in a supported digit script.
     * @param  string  $code  OTP code in a supported digit script.
     * @param  int  $tenantId  Tenant id resolved from the request host.
     * @return Lead Verified tenant-owned lead.
     */
    public function verifyCode(string $phone, string $code, int $tenantId): Lead
    {
        $phoneNormalized = $this->normalizePhone($phone);
        $codeNormalized = NumberNormalizer::toLatinDigits($code);

        $otp = OtpCode::query()
            ->where('tenant_id', '=', $tenantId)
            ->where('phone_normalized', '=', $phoneNormalized)
            ->where('purpose', '=', 'lead_login')
            ->whereNull('verified_at')
            ->where('expires_at', '>=', Carbon::now())
            ->orderByDesc('created_at')
            ->first();

        if (! $otp) {
            abort(422, __('auth.invalid_lead_otp'));
        }

        if ($otp->attempts >= config('hairvision.otp.max_attempts')) {
            abort(422, __('auth.too_many_otp_attempts'));
        }

        if (! Hash::check($codeNormalized, $otp->code_hash)) {
            $newAttempts = $otp->attempts + 1;
            $otp->increment('attempts');

            if ($newAttempts >= config('hairvision.otp.max_attempts')) {
                $otp->update(['verified_at' => Carbon::now()]);
            }

            abort(422, __('auth.invalid_lead_otp'));
        }

        $otp->update(['verified_at' => Carbon::now()]);

        $lead = Lead::updateOrCreate(
            ['tenant_id' => $tenantId, 'phone_normalized' => $phoneNormalized],
            [
                'tenant_id' => $tenantId,
                'phone' => $phoneNormalized,
                'phone_normalized' => $phoneNormalized,
                'phone_verified_at' => Carbon::now(),
                'status' => 'verified',
            ]
        );

        return $lead;
    }

    /**
     * Normalize a phone number to Latin digits.
     *
     * @param  string  $phone  Phone number in a supported digit script.
     * @return string Latin-digit phone string.
     */
    private function normalizePhone(string $phone): string
    {
        return NumberNormalizer::digitsOnly($phone);
    }
}
