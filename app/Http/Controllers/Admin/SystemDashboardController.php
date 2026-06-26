<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders platform-level management overview for authorized users.
 *
 * @since 1.0.0
 */
class SystemDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.system.dashboard', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'counts' => [
                'users' => User::query()->count(),
                'roles' => Role::query()->count(),
                'settings' => SystemSetting::query()->count(),
                'sms_gateways' => SmsGateway::query()->count(),
                'payment_gateways' => PaymentGateway::query()->count(),
                'plans' => Plan::query()->count(),
                'open_invoices' => Invoice::query()->whereIn('status', ['pending', 'failed'])->count(),
                'active_subscriptions' => Subscription::query()->where('status', 'active')->count(),
            ],
        ]);
    }
}
