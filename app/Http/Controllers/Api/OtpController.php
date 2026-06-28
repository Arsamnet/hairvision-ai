<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Services\OtpService;
use App\Support\NumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Handles public visitor OTP request and verification endpoints.
 *
 * Side effects include creating hashed OTP records, rate-limit hits, verified
 * lead records, and a verified lead id in the visitor session.
 *
 * @since 1.0.0
 */
class OtpController extends Controller
{
    /**
     * Request a one-time password for a tenant-scoped visitor phone number.
     *
     * @param  RequestOtpRequest  $request  Validated OTP request payload.
     * @param  OtpService  $otpService  Service that normalizes digits and stores hashed OTPs.
     */
    public function request(RequestOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $tenant = app('currentTenant');
        $phoneInput = (string) $request->post('phone');
        $phone = $otpService->normalizeDigits($phoneInput);

        if (! $otpService->hasOnlySupportedDigits($phoneInput) || strlen($phone) !== 11) {
            return response()->json([
                'success' => false,
                'message' => 'شماره موبایل باید فقط شامل ۱۱ رقم باشد.',
            ], 422);
        }

        $rateLimitKey = 'otp-request:'.$tenant->id.':'.$phone.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.otp.max_requests'))) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست‌های کد تایید زیاد شده است. لطفا کمی بعد دوباره تلاش کنید.',
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.otp.decay_seconds'));

        $otp = $otpService->requestCode($phone, $request->ip(), $tenant->id);

        $payload = [
            'success' => true,
            'message' => 'کد تایید ارسال شد.',
            'expires_in' => 120,
        ];

        if (app()->hasDebugModeEnabled() && isset($otp->debug_code)) {
            $payload['debug_sms'] = 'کد تایید HairVision: '.NumberNormalizer::toPersianDigits($otp->debug_code);
        }

        return response()->json($payload);
    }

    /**
     * Verify an OTP and attach the verified lead to the visitor session.
     *
     * @param  VerifyOtpRequest  $request  Validated OTP verification payload.
     * @param  OtpService  $otpService  Service that verifies hashes and creates leads.
     */
    public function verify(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $tenant = app('currentTenant');
        $phoneInput = (string) $request->post('phone');
        $codeInput = (string) $request->post('code');
        $phone = $otpService->normalizeDigits($phoneInput);
        $code = $otpService->normalizeDigits($codeInput);

        if (! $otpService->hasOnlySupportedDigits($phoneInput) || strlen($phone) !== 11) {
            return response()->json([
                'success' => false,
                'message' => 'شماره موبایل باید فقط شامل ۱۱ رقم باشد.',
            ], 422);
        }

        if (! $otpService->hasOnlySupportedDigits($codeInput) || strlen($code) !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'کد تایید باید فقط شامل ۶ رقم باشد.',
            ], 422);
        }

        $lead = $otpService->verifyCode($phone, $code, $tenant->id);

        $request->session()->put('lead_id', $lead->id);

        return response()->json([
            'success' => true,
            'lead' => ['id' => $lead->uuid, 'phone' => $lead->phone],
            'message' => 'شماره موبایل تایید شد.',
        ]);
    }

}
