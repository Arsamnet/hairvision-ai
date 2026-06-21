<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    public function request(Request $request, OtpService $otpService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ], [
            'phone.required' => 'شماره موبایل را وارد کنید.',
            'phone.string' => 'شماره موبایل باید به صورت متن عددی ارسال شود.',
        ]);

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
            $payload['debug_sms'] = 'کد تایید HairVision: '.$this->toPersianDigits($otp->debug_code);
        }

        return response()->json($payload);
    }

    public function verify(Request $request, OtpService $otpService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ], [
            'phone.required' => 'شماره موبایل را وارد کنید.',
            'phone.string' => 'شماره موبایل باید به صورت متن عددی ارسال شود.',
            'code.required' => 'کد تایید را وارد کنید.',
            'code.string' => 'کد تایید باید به صورت متن عددی ارسال شود.',
        ]);

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

    private function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }
}
