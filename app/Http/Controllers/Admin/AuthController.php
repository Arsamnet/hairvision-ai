<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\RequestAdminOtpRequest;
use App\Http\Requests\Admin\Auth\VerifyAdminOtpRequest;
use App\Services\Admin\AdminTenantContext;
use App\Services\Auth\AdminOtpService;
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
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View|RedirectResponse Rendered response view.
     *
     * @since 1.0.0
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        $scope = 'admin.auth.login';

        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Request a new admin OTP code.
     *
     * @param  RequestAdminOtpRequest  $request  Incoming HTTP request.
     * @param  AdminOtpService  $otpService  The otp service value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function requestOtp(RequestAdminOtpRequest $request, AdminOtpService $otpService): RedirectResponse
    {
        $scope = 'admin.auth.request_otp';

        $phone = (string) $request->input('phone');
        $normalized = $otpService->normalizePhone($phone);
        $phoneHash = hash('sha256', $normalized);
        $ipHash = hash('sha256', (string) $request->ip());
        $rateLimitKey = 'admin-otp-request:'.$normalized.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('hairvision.admin_otp.max_requests'))) {
            return back()
                ->withInput(['phone' => $phone])
                ->withErrors(['phone' => __('auth.too_many_login_requests')]);
        }

        RateLimiter::hit($rateLimitKey, config('hairvision.admin_otp.decay_seconds'));

        $otpService->requestCode($phone, $request->ip());

        $request->session()->put('admin_pending_phone', $normalized);

        if ($debugSms = $otpService->lastDebugSms()) {
            $request->session()->flash('debug_sms', $debugSms);

        }

        return redirect()->route('admin.otp.form');
    }

    /**
     * Show the admin OTP verification form.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View|RedirectResponse Rendered response view.
     *
     * @since 1.0.0
     */
    public function showVerify(Request $request): View|RedirectResponse
    {
        $scope = 'admin.auth.show_verify';

        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $request->session()->has('admin_pending_phone')) {
            return redirect()->route('admin.login');
        }

        $phone = (string) $request->session()->get('admin_pending_phone');

        return view('admin.auth.verify', [
            'phone' => $phone,
            'debugSms' => $request->session()->get('debug_sms'),
            'expiresIn' => (int) config('hairvision.admin_otp.expires_seconds', 300),
        ]);
    }

    /**
     * Verify the admin OTP code and log the user in.
     *
     * @param  VerifyAdminOtpRequest  $request  Incoming HTTP request.
     * @param  AdminOtpService  $otpService  The otp service value.
     * @param  AdminTenantContext  $tenantContext  The tenant context value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function verify(
        VerifyAdminOtpRequest $request,
        AdminOtpService $otpService,
        AdminTenantContext $tenantContext
    ): RedirectResponse {
        $scope = 'admin.auth.verify';

        $phone = (string) $request->input('phone');
        $code = (string) $request->input('code');
        $normalized = $otpService->normalizePhone($phone);
        $phoneHash = hash('sha256', $normalized);
        $ipHash = hash('sha256', (string) $request->ip());
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

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Log the admin user out.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function logout(Request $request): RedirectResponse
    {
        $scope = 'admin.auth.logout';
        $userId = Auth::id();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
