<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Services\OtpService;
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
        $phone = (string) $request->validated('phone');

        $rateLimitKey = 'otp-request:'.$tenant->id.':'.$phone.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.otp.max_requests'))) {
            return response()->json([
                'success' => false,
                'message' => __('auth.too_many_otp_requests'),
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.otp.decay_seconds'));

        $otpService->requestCode($phone, $request->ip(), $tenant->id);

        $debugSms = $otpService->lastDebugSms();

        return response()->json(array_filter([
            'success' => true,
            'message' => __('auth.otp_sent'),
            'expires_in' => 120,
            'debug_sms' => $debugSms,
        ], fn ($value) => $value !== null));
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
        $phone = (string) $request->validated('phone');
        $code = (string) $request->validated('code');

        $lead = $otpService->verifyCode($phone, $code, $tenant->id);

        $request->session()->put('lead_id', $lead->id);

        return response()->json([
            'success' => true,
            'lead' => ['id' => $lead->uuid, 'phone' => $lead->phone],
            'message' => __('auth.phone_verified'),
        ]);
    }
}
