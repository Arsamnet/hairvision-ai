<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Models\Tenant;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Handles public visitor OTP endpoints.
 *
 * @since 1.0.0
 */
class OtpController extends Controller
{
    /**
     * Request a tenant-scoped OTP code.
     */
    public function request(RequestOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $scope = 'public.otp.request';

        $tenant = $this->tenantFromRequest($request, $scope);
        $phone = (string) $request->validated('phone');
        $ipAddress = (string) $request->ip();
        $phoneHash = hash('sha256', $phone);
        $ipHash = $ipAddress !== '' ? hash('sha256', $ipAddress) : null;
        $rateLimitKey = 'otp-request:'.$tenant->id.':'.$phone.':'.$ipAddress;

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.otp.max_requests'))) {
            return response()->json([
                'success' => false,
                'message' => __('auth.too_many_otp_requests'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.otp.decay_seconds'));

        try {
            $otpService->requestCode($phone, $ipAddress, $tenant->id, $request);
        } catch (Throwable $exception) {
            throw $exception;
        }

        $debugSms = $otpService->lastDebugSms();

        $expiresIn = (int) config('hairvision.otp.expires_seconds', 120);

        return response()->json(array_filter([
            'success' => true,
            'message' => __('auth.otp_sent'),
            'expires_in' => $expiresIn,
            'debug_sms' => $debugSms,
        ], fn ($value) => $value !== null));
    }

    /**
     * Verify an OTP code and store the verified lead in session.
     */
    public function verify(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $scope = 'public.otp.verify';

        $tenant = $this->tenantFromRequest($request, $scope);
        $phone = (string) $request->validated('phone');
        $code = (string) $request->validated('code');
        $phoneHash = hash('sha256', $phone);

        try {
            $lead = $otpService->verifyCode($phone, $code, $tenant->id, $request);
        } catch (Throwable $exception) {
            throw $exception;
        }

        $request->session()->put('lead_id', $lead->id);

        return response()->json([
            'success' => true,
            'lead' => [
                'id' => $lead->uuid,
                'phone' => $lead->phone,
            ],
            'message' => __('auth.phone_verified'),
        ]);
    }

    /**
     * Get the tenant resolved by tenant middleware.
     */
    private function tenantFromRequest(Request $request, string $scope): Tenant
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}
