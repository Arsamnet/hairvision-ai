<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Otp\OtpSenderInterface;
use App\Services\OtpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Random\RandomException;

/**
 * Creates and verifies hashed OTP codes for dashboard session login.
 *
 * @since 1.1.0
 */
class AdminOtpService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly OtpSenderInterface $otpSender,
    ) {}

    /**
     * Create a single-use dashboard login OTP for an active user.
     *
     * @throws RandomException
     * @throws ValidationException
     */
    public function requestCode(string $phone, string $ipAddress): OtpCode
    {
        $phoneNormalized = $this->normalizePhone($phone);
        $user = $this->findActiveUser($phoneNormalized);
        $tenantId = $this->otpTenantFor($user)?->id;

        if (! $tenantId) {
            throw ValidationException::withMessages([
                'phone' => 'برای این کاربر هیچ کلینیک فعالی تعریف نشده است.',
            ]);
        }

        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'tenant_id' => $tenantId,
            'phone_normalized' => $phoneNormalized,
            'code_hash' => Hash::make($code),
            'purpose' => 'admin_login',
            'expires_at' => Carbon::now()->addMinutes(5),
            'ip_address' => $ipAddress,
            'attempts' => 0,
        ]);

        $this->otpSender->send($phoneNormalized, $code);

        if (app()->hasDebugModeEnabled()) {
            $otp->debug_code = $code;
        }

        return $otp;
    }

    /**
     * Verify a dashboard OTP and return the authenticated user.
     *
     * @throws ValidationException
     */
    public function verifyCode(string $phone, string $code): User
    {
        $phoneNormalized = $this->normalizePhone($phone);
        $codeNormalized = $this->otpService->normalizeDigits($code);
        $user = $this->findActiveUser($phoneNormalized);

        $otp = OtpCode::query()
            ->where('tenant_id', '=', $this->otpTenantFor($user)?->id)
            ->where('phone_normalized', '=', $phoneNormalized)
            ->where('purpose', '=', 'admin_login')
            ->whereNull('verified_at')
            ->where('expires_at', '>=', Carbon::now())
            ->latest()
            ->first();

        if (! $otp || $otp->attempts >= config('hairvision.admin_otp.max_attempts')) {
            throw ValidationException::withMessages([
                'code' => 'کد ورود معتبر نیست. لطفا دوباره کد دریافت کنید.',
            ]);
        }

        if (! Hash::check($codeNormalized, $otp->code_hash)) {
            $newAttempts = $otp->attempts + 1;
            $otp->increment('attempts');

            if ($newAttempts >= config('hairvision.admin_otp.max_attempts')) {
                $otp->update(['verified_at' => Carbon::now()]);
            }

            throw ValidationException::withMessages([
                'code' => 'کد ورود معتبر نیست. لطفا کد را بررسی کنید.',
            ]);
        }

        $otp->update(['verified_at' => Carbon::now()]);

        return $user;
    }

    /**
     * Validate and normalize a mobile number.
     *
     * @throws ValidationException
     */
    public function normalizePhone(string $phone): string
    {
        $normalized = $this->otpService->normalizeDigits($phone);

        if (! $this->otpService->hasOnlySupportedDigits($phone) || strlen($normalized) !== 11) {
            throw ValidationException::withMessages([
                'phone' => 'شماره موبایل باید فقط شامل ۱۱ رقم باشد.',
            ]);
        }

        return preg_replace('/[^0-9]/', '', $normalized);
    }

    /**
     * Find an active dashboard user by mobile number.
     *
     * @throws ValidationException
     */
    private function findActiveUser(string $phoneNormalized): User
    {
        $user = User::query()
            ->where('phone_normalized', '=', $phoneNormalized)
            ->first();

        if (! $user || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'phone' => 'کاربر فعالی با این شماره موبایل پیدا نشد.',
            ]);
        }

        return $user;
    }

    /**
     * Resolve the tenant bucket used for storing the OTP row.
     */
    private function otpTenantFor(User $user): ?Tenant
    {
        if ($user->isSuperAdmin()) {
            return Tenant::query()->where('status', 'active')->orderBy('id')->first();
        }

        return $user->tenants()
            ->wherePivot('status', 'active')
            ->orderBy('tenants.id')
            ->first();
    }
}
