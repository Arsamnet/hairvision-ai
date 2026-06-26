<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sms\CreateSmsProviderAccountAction;
use App\Actions\Sms\UpdateSmsProviderAccountAction;
use App\Enums\SmsProviderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sms\StoreSmsProviderAccountRequest;
use App\Http\Requests\Admin\Sms\UpdateSmsProviderAccountRequest;
use App\Models\SmsProviderAccount;
use App\Models\SmsProviderType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manages SMS provider accounts in the dashboard.
 *
 * @since 1.2.0
 */
class SmsProviderAccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SmsProviderAccount::class);

        $query = SmsProviderAccount::query()
            ->with(['type', 'owner'])
            ->withCount([
                'deliveryLogs as sent_count' => fn ($query) => $query->where('status', 'sent'),
                'deliveryLogs as failed_count' => fn ($query) => $query->where('status', 'failed'),
            ]);

        if (! $request->user()->isSuperAdmin()) {
            $query->where(function ($query) use ($request): void {
                $query->where('scope', SmsProviderScope::System->value)
                    ->orWhere('owner_user_id', $request->user()->id);
            });
        }

        $query->when($request->filled('provider_type'), fn ($query) => $query->where('sms_provider_type_id', $request->integer('provider_type')))
            ->when($request->filled('scope'), fn ($query) => $query->where('scope', $request->string('scope')->toString()))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->when($request->user()->isSuperAdmin() && $request->filled('owner_user_id'), fn ($query) => $query->where('owner_user_id', $request->integer('owner_user_id')));

        return view('admin.sms.providers.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'providers' => $query->latest()->get(),
            'providerTypes' => SmsProviderType::query()->orderBy('name')->get(),
            'owners' => $request->user()->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SmsProviderAccount::class);

        return view('admin.sms.providers.create', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'providerTypes' => SmsProviderType::query()->where('is_active', true)->orderBy('name')->get(),
            'scopes' => $request->user()->isSuperAdmin()
                ? [SmsProviderScope::User->value => 'اختصاصی کاربر', SmsProviderScope::System->value => 'سیستمی']
                : [SmsProviderScope::User->value => 'اختصاصی کاربر'],
        ]);
    }

    public function store(StoreSmsProviderAccountRequest $request, CreateSmsProviderAccountAction $action): RedirectResponse
    {
        $provider = $action->execute($request->validated(), $request->user());

        if ($request->input('_redirect_to') === 'settings') {
            return redirect()->route('admin.settings')->with('status', 'سرویس پیامک ذخیره شد.');
        }

        return redirect()->route('admin.sms.providers.edit', $provider)->with('status', 'سرویس پیامک ذخیره شد.');
    }

    public function edit(Request $request, SmsProviderAccount $provider): View
    {
        Gate::authorize('update', $provider);

        return view('admin.sms.providers.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'provider' => $provider->load(['type', 'owner'])->loadCount([
                'deliveryLogs as sent_count' => fn ($query) => $query->where('status', 'sent'),
                'deliveryLogs as failed_count' => fn ($query) => $query->where('status', 'failed'),
            ]),
        ]);
    }

    public function update(UpdateSmsProviderAccountRequest $request, SmsProviderAccount $provider, UpdateSmsProviderAccountAction $action): RedirectResponse
    {
        $action->execute($provider, $request->validated());

        return redirect()->route('admin.sms.providers.edit', $provider)->with('status', 'سرویس پیامک به‌روزرسانی شد.');
    }

    public function destroy(Request $request, SmsProviderAccount $provider): RedirectResponse
    {
        Gate::authorize('delete', $provider);

        $provider->delete();

        return redirect()->route('admin.sms.providers.index')->with('status', 'سرویس پیامک حذف شد.');
    }
}
