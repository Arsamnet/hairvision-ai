<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\RequestAdminOtpRequest;
use App\Http\Requests\Admin\Auth\VerifyAdminOtpRequest;
use App\Services\Admin\AdminTenantContext;
use App\Services\Auth\AdminOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Handles mobile OTP authentication for the dashboard.
 *
 * @since 1.1.0
 */
class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function requestOtp(RequestAdminOtpRequest $request, AdminOtpService $otpService): RedirectResponse
    {
        $phone = (string) $request->input('phone');
        $normalized = $otpService->normalizePhone($phone);
        $rateLimitKey = 'admin-otp-request:'.$normalized.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.admin_otp.max_requests'))) {
            return back()
                ->withInput(['phone' => $phone])
                ->withErrors(['phone' => __('auth.too_many_login_requests')]);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.admin_otp.decay_seconds'));

        $otpService->requestCode($phone, $request->ip());
        $request->session()->put('admin_pending_phone', $normalized);

        Log::info('Admin OTP requested.', [
            'phone_hash' => hash('sha256', $normalized),
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.otp.form');
    }

    public function showVerify(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        if (! session('admin_pending_phone')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.verify', [
            'phone' => session('admin_pending_phone'),
        ]);
    }

    public function verify(VerifyAdminOtpRequest $request, AdminOtpService $otpService, AdminTenantContext $tenantContext): RedirectResponse
    {
        $phone = (string) $request->input('phone');
        $code = (string) $request->input('code');
        $normalized = $otpService->normalizePhone($phone);
        $rateLimitKey = 'admin-otp-verify:'.$normalized.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.admin_otp.max_verify_attempts'))) {
            return back()
                ->withInput(['phone' => $normalized])
                ->withErrors(['code' => __('auth.too_many_verify_requests')]);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.admin_otp.verify_decay_seconds'));

        $user = $otpService->verifyCode($phone, $code);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('admin_pending_phone');
        $tenantContext->current($request, $user);
        RateLimiter::clear($rateLimitKey);

        Log::info('Admin OTP verified.', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

}
