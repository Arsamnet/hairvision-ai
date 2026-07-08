<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\RequestAdminOtpRequest;
use App\Http\Requests\Admin\Auth\VerifyAdminOtpRequest;
use App\Services\Admin\AdminTenantContext;
use App\Services\Auth\AdminOtpService;
use App\Support\DebugStepLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Handles dashboard OTP authentication.
 *
 * @since 1.0.0
 */
class AuthController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        $scope = 'admin.auth.login';

        DebugStepLogger::step($request, $scope, 'Request received', [
            'authenticated' => Auth::check(),
        ]);

        if (Auth::check()) {
            DebugStepLogger::step($request, $scope, 'Authenticated user redirected', [
                'user_id' => Auth::id(),
                'target' => 'admin.dashboard',
            ]);

            return redirect()->route('admin.dashboard');
        }

        DebugStepLogger::step($request, $scope, 'Login view rendered');

        return view('admin.auth.login');
    }

    /**
     * Request a new admin OTP code.
     */
    public function requestOtp(RequestAdminOtpRequest $request, AdminOtpService $otpService): RedirectResponse
    {
        $scope = 'admin.auth.request_otp';

        DebugStepLogger::step($request, $scope, 'Request received');

        $phone = (string) $request->input('phone');
        $normalized = $otpService->normalizePhone($phone);
        $phoneHash = hash('sha256', $normalized);
        $ipHash = hash('sha256', (string) $request->ip());
        $rateLimitKey = 'admin-otp-request:'.$normalized.':'.$request->ip();

        DebugStepLogger::step($request, $scope, 'Phone normalized', [
            'phone_hash' => $phoneHash,
            'ip_hash' => $ipHash,
        ]);

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.admin_otp.max_requests'))) {
            DebugStepLogger::step($request, $scope, 'Rate limit exceeded', [
                'phone_hash' => $phoneHash,
                'ip_hash' => $ipHash,
                'max_attempts' => config('hairvision.admin_otp.max_requests'),
            ]);

            return back()
                ->withInput(['phone' => $phone])
                ->withErrors(['phone' => __('auth.too_many_login_requests')]);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.admin_otp.decay_seconds'));

        DebugStepLogger::step($request, $scope, 'Rate limit hit recorded', [
            'decay_seconds' => config('hairvision.admin_otp.decay_seconds'),
        ]);

        $otpService->requestCode($phone, $request->ip());

        DebugStepLogger::step($request, $scope, 'OTP requested', [
            'phone_hash' => $phoneHash,
        ]);

        $request->session()->put('admin_pending_phone', $normalized);

        DebugStepLogger::step($request, $scope, 'Pending phone stored in session', [
            'phone_hash' => $phoneHash,
        ]);

        if ($debugSms = $otpService->lastDebugSms()) {
            $request->session()->flash('debug_sms', $debugSms);

            DebugStepLogger::step($request, $scope, 'Debug SMS flashed to session', [
                'has_debug_sms' => true,
            ]);
        }

        DebugStepLogger::step($request, $scope, 'Redirecting to OTP form', [
            'target' => 'admin.otp.form',
        ]);

        return redirect()->route('admin.otp.form');
    }

    /**
     * Show the admin OTP verification form.
     */
    public function showVerify(Request $request): View|RedirectResponse
    {
        $scope = 'admin.auth.show_verify';

        DebugStepLogger::step($request, $scope, 'Request received', [
            'authenticated' => Auth::check(),
            'has_pending_phone' => $request->session()->has('admin_pending_phone'),
        ]);

        if (Auth::check()) {
            DebugStepLogger::step($request, $scope, 'Authenticated user redirected', [
                'user_id' => Auth::id(),
                'target' => 'admin.dashboard',
            ]);

            return redirect()->route('admin.dashboard');
        }

        if (! $request->session()->has('admin_pending_phone')) {
            DebugStepLogger::step($request, $scope, 'Pending phone missing', [
                'target' => 'admin.login',
            ]);

            return redirect()->route('admin.login');
        }

        $phone = (string) $request->session()->get('admin_pending_phone');

        DebugStepLogger::step($request, $scope, 'Verification view rendered', [
            'phone_hash' => hash('sha256', $phone),
            'has_debug_sms' => $request->session()->has('debug_sms'),
        ]);

        return view('admin.auth.verify', [
            'phone' => $phone,
            'debugSms' => $request->session()->get('debug_sms'),
        ]);
    }

    /**
     * Verify the admin OTP code and log the user in.
     */
    public function verify(
        VerifyAdminOtpRequest $request,
        AdminOtpService $otpService,
        AdminTenantContext $tenantContext
    ): RedirectResponse {
        $scope = 'admin.auth.verify';

        DebugStepLogger::step($request, $scope, 'Request received');

        $phone = (string) $request->input('phone');
        $code = (string) $request->input('code');
        $normalized = $otpService->normalizePhone($phone);
        $phoneHash = hash('sha256', $normalized);
        $ipHash = hash('sha256', (string) $request->ip());
        $rateLimitKey = 'admin-otp-verify:'.$normalized.':'.$request->ip();

        DebugStepLogger::step($request, $scope, 'Phone normalized', [
            'phone_hash' => $phoneHash,
            'ip_hash' => $ipHash,
            'code_length' => strlen($code),
        ]);

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.admin_otp.max_verify_attempts'))) {
            DebugStepLogger::step($request, $scope, 'Rate limit exceeded', [
                'phone_hash' => $phoneHash,
                'ip_hash' => $ipHash,
                'max_attempts' => config('hairvision.admin_otp.max_verify_attempts'),
            ]);

            return back()
                ->withInput(['phone' => $normalized])
                ->withErrors(['code' => __('auth.too_many_verify_requests')]);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.admin_otp.verify_decay_seconds'));

        DebugStepLogger::step($request, $scope, 'Rate limit hit recorded', [
            'verify_decay_seconds' => config('hairvision.admin_otp.verify_decay_seconds'),
        ]);

        $user = $otpService->verifyCode($phone, $code);

        DebugStepLogger::step($request, $scope, 'OTP verified', [
            'user_id' => $user->id,
            'phone_hash' => $phoneHash,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        DebugStepLogger::step($request, $scope, 'User logged in and session regenerated', [
            'user_id' => $user->id,
        ]);

        $request->session()->forget('admin_pending_phone');

        DebugStepLogger::step($request, $scope, 'Pending phone removed from session');

        $tenantContext->current($request, $user);

        DebugStepLogger::step($request, $scope, 'Admin tenant context resolved', [
            'user_id' => $user->id,
        ]);

        RateLimiter::clear($rateLimitKey);

        DebugStepLogger::step($request, $scope, 'Verify rate limit cleared', [
            'phone_hash' => $phoneHash,
        ]);

        DebugStepLogger::step($request, $scope, 'Redirecting to dashboard', [
            'target' => 'admin.dashboard',
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Log the admin user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        $scope = 'admin.auth.logout';
        $userId = Auth::id();

        DebugStepLogger::step($request, $scope, 'Request received', [
            'user_id' => $userId,
            'authenticated' => Auth::check(),
        ]);

        Auth::logout();

        DebugStepLogger::step($request, $scope, 'User logged out', [
            'user_id' => $userId,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DebugStepLogger::step($request, $scope, 'Session invalidated and token regenerated');

        DebugStepLogger::step($request, $scope, 'Redirecting to login', [
            'target' => 'admin.login',
        ]);

        return redirect()->route('admin.login');
    }
}