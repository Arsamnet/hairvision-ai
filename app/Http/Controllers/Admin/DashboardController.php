<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SmsProviderScope;
use App\Http\Controllers\Controller;
use App\Models\SmsProviderAccount;
use App\Models\SmsProviderType;
use App\Models\Tenant;
use App\Services\Admin\AdminTenantContext;
use App\Services\Admin\LeadDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders dashboard summary pages.
 *
 * @since 1.1.0
 */
class DashboardController extends Controller
{
    public function index(Request $request, LeadDashboardService $leads, AdminTenantContext $tenantContext): View
    {
        $tenant = $tenantContext->fromFilter($request, $request->user());
        $query = $leads->baseQuery($request->user(), $tenant);

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'leadCount' => (clone $query)->count(),
            'newLeadCount' => (clone $query)->where('crm_stage', 'new_lead')->count(),
            'followUpCount' => (clone $query)->where('crm_stage', 'needs_follow_up')->count(),
            'convertedCount' => (clone $query)->where('crm_stage', 'converted')->count(),
            'recentLeads' => (clone $query)->with('tenant')->latest()->limit(6)->get(),
        ]);
    }

    public function reports(Request $request, LeadDashboardService $leads, AdminTenantContext $tenantContext): View
    {
        $tenant = $tenantContext->fromFilter($request, $request->user());
        $query = $leads->baseQuery($request->user(), $tenant);

        return view('admin.reports', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'stageCounts' => (clone $query)
                ->selectRaw('crm_stage, count(*) as aggregate')
                ->groupBy('crm_stage')
                ->pluck('aggregate', 'crm_stage'),
            'tenantCounts' => $request->user()->isSuperAdmin()
                ? Tenant::query()->withCount('leads')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function settings(Request $request): View
    {
        $user = $request->user();

        return view('admin.settings', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'smsSetting' => $user->smsSetting()->firstOrCreate(['user_id' => $user->id]),
            'smsProviders' => SmsProviderAccount::query()
                ->with('type')
                ->where('is_active', true)
                ->where(function ($query) use ($user): void {
                    $query->where('scope', SmsProviderScope::System->value)
                        ->orWhere('owner_user_id', $user->id);
                })
                ->orderBy('name')
                ->get(),
            'smsProviderTypes' => SmsProviderType::query()->where('is_active', true)->orderBy('name')->get(),
            'smsScopes' => $user->isSuperAdmin()
                ? [SmsProviderScope::User->value => 'اختصاصی کاربر', SmsProviderScope::System->value => 'سیستمی']
                : [SmsProviderScope::User->value => 'اختصاصی کاربر'],
        ]);
    }
}
