<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\OtpCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class OtpService
{
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

        return $otp;
    }

    public function verifyCode(string $phone, string $code, int $tenantId): Lead
    {
        $phoneNormalized = $this->normalizePhone($phone);
        $codeNormalized = $this->normalizeDigits($code);

        $otp = OtpCode::query()
            ->where('tenant_id', '=', $tenantId)
            ->where('phone_normalized', '=', $phoneNormalized)
            ->where('purpose', '=', 'lead_login')
            ->where('expires_at', '>=', Carbon::now())
            ->orderByDesc('created_at')
            ->first();

        if (! $otp) {
            abort(422, 'کد تایید معتبر نیست. لطفا کد را بررسی کنید یا دوباره کد بگیرید.');
        }

        if ($otp->attempts >= config('hairvision.otp.max_attempts')) {
            abort(422, 'تعداد تلاش‌ها بیش از حد مجاز است. لطفا دوباره کد دریافت کنید.');
        }

        if (! Hash::check($codeNormalized, $otp->code_hash)) {
            $otp->increment('attempts');

            abort(422, 'کد تایید معتبر نیست. لطفا کد را بررسی کنید یا دوباره کد بگیرید.');
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

    public function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    public function hasOnlySupportedDigits(string $value): bool
    {
        return preg_match('/^[0-9۰-۹٠-٩]+$/u', $value) === 1;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $this->normalizeDigits($phone));
    }
}
