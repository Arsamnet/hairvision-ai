<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows system-wide reports separated from user-scoped reports.
 *
 * @since 1.0.0
 */
class ManagementReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.system.reports.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'metrics' => [
                'users' => User::query()->count(),
                'leads' => Lead::query()->count(),
                'paid_revenue' => Invoice::query()->where('status', 'paid')->sum('amount'),
                'payments' => Payment::query()->count(),
                'active_subscriptions' => Subscription::query()->where('status', 'active')->count(),
                'sms_gateways' => SmsGateway::query()->count(),
            ],
        ]);
    }
}
