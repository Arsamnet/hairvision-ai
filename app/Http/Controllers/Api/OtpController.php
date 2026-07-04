<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Models\Tenant;
use App\Services\OtpService;
use App\Support\DebugStepLogger;
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

        DebugStepLogger::step($request, $scope, 'Request received');

        $tenant = $this->tenantFromRequest($request, $scope);
        $phone = (string) $request->validated('phone');
        $ipAddress = (string) $request->ip();
        $phoneHash = hash('sha256', $phone);
        $ipHash = $ipAddress !== '' ? hash('sha256', $ipAddress) : null;
        $rateLimitKey = 'otp-request:'.$tenant->id.':'.$phone.':'.$ipAddress;

        DebugStepLogger::step($request, $scope, 'Tenant and payload resolved', [
            'tenant_id' => $tenant->id,
            'phone_hash' => $phoneHash,
            'ip_hash' => $ipHash,
        ]);

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.otp.max_requests'))) {
            DebugStepLogger::step($request, $scope, 'Rate limit exceeded', [
                'tenant_id' => $tenant->id,
                'phone_hash' => $phoneHash,
                'ip_hash' => $ipHash,
                'max_attempts' => config('hairvision.otp.max_requests'),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auth.too_many_otp_requests'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.otp.decay_seconds'));

        DebugStepLogger::step($request, $scope, 'Rate limit hit recorded', [
            'tenant_id' => $tenant->id,
            'decay_seconds' => config('hairvision.otp.decay_seconds'),
        ]);

        try {
            $otpService->requestCode($phone, $ipAddress, $tenant->id, $request);
        } catch (Throwable $exception) {
            DebugStepLogger::step($request, $scope, 'OTP request failed', [
                'tenant_id' => $tenant->id,
                'phone_hash' => $phoneHash,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        DebugStepLogger::step($request, $scope, 'OTP requested', [
            'tenant_id' => $tenant->id,
            'phone_hash' => $phoneHash,
        ]);

        $debugSms = $otpService->lastDebugSms();

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'tenant_id' => $tenant->id,
            'has_debug_sms' => $debugSms !== null,
            'expires_in' => 120,
        ]);

        return response()->json(array_filter([
            'success' => true,
            'message' => __('auth.otp_sent'),
            'expires_in' => 120,
            'debug_sms' => $debugSms,
        ], fn ($value) => $value !== null));
    }

    /**
     * Verify an OTP code and store the verified lead in session.
     */
    public function verify(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $scope = 'public.otp.verify';

        DebugStepLogger::step($request, $scope, 'Request received');

        $tenant = $this->tenantFromRequest($request, $scope);
        $phone = (string) $request->validated('phone');
        $code = (string) $request->validated('code');
        $phoneHash = hash('sha256', $phone);

        DebugStepLogger::step($request, $scope, 'Tenant and payload resolved', [
            'tenant_id' => $tenant->id,
            'phone_hash' => $phoneHash,
            'code_length' => strlen($code),
        ]);

        try {
            $lead = $otpService->verifyCode($phone, $code, $tenant->id, $request);
        } catch (Throwable $exception) {
            DebugStepLogger::step($request, $scope, 'OTP verification failed', [
                'tenant_id' => $tenant->id,
                'phone_hash' => $phoneHash,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        DebugStepLogger::step($request, $scope, 'OTP verified', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
        ]);

        $request->session()->put('lead_id', $lead->id);

        DebugStepLogger::step($request, $scope, 'Lead stored in session', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
        ]);

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'tenant_id' => $tenant->id,
            'lead_uuid' => $lead->uuid,
        ]);

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
            DebugStepLogger::step($request, $scope, 'Tenant missing from request attributes');

            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}