<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sms\SendSmsAction;
use App\Enums\SmsProviderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sms\UpdateUserSmsSettingsRequest;
use App\Models\SmsProviderAccount;
use App\Models\UserSmsSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Handles the authenticated user's SMS provider preference.
 *
 * @since 1.2.0
 */
class UserSmsSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('admin.account.sms-settings', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'setting' => $user->smsSetting()->firstOrCreate(['user_id' => $user->id]),
            'providers' => SmsProviderAccount::query()
                ->with('type')
                ->where('is_active', true)
                ->where(function ($query) use ($user): void {
                    $query->where('scope', SmsProviderScope::System->value)->orWhere('owner_user_id', $user->id);
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateUserSmsSettingsRequest $request): RedirectResponse
    {
        UserSmsSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'default_sms_provider_account_id' => $request->input('default_sms_provider_account_id') ?: null,
                'fallback_to_system' => $request->boolean('fallback_to_system'),
            ]
        );

        $route = $request->input('_redirect_to') === 'settings'
            ? 'admin.settings'
            : 'admin.account.sms-settings.edit';

        return redirect()->route($route)->with('status', 'تنظیمات پیامک ذخیره شد.');
    }

    public function testSend(Request $request, SendSmsAction $sendSms): RedirectResponse
    {
        $user = $request->user();
        $rateLimitKey = 'sms-test-send:'.$user->id.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return back()->withErrors(['sms_test' => 'ارسال آزمایشی زیاد شده است. لطفا کمی بعد دوباره تلاش کنید.']);
        }

        if (blank($user->phone_normalized)) {
            return back()->withErrors(['sms_test' => 'برای حساب شما شماره موبایل ثبت نشده است.']);
        }

        RateLimiter::hit($rateLimitKey, 600);

        $result = $sendSms->execute(
            $user,
            $user->phone_normalized,
            'پیام آزمایشی HairVision',
            'test_sms'
        );

        if ($result->status->value !== 'sent') {
            return back()->withErrors(['sms_test' => 'ارسال آزمایشی ناموفق بود. تنظیمات سرویس پیامک را بررسی کنید.']);
        }

        return back()->with('status', 'پیامک آزمایشی ارسال شد.');
    }
}
