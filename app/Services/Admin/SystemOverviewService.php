<?php

namespace App\Services\Admin;

use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;

/**
 * Builds reusable platform dashboard and management report metrics.
 *
 * @since 1.0.0
 */
class SystemOverviewService
{
    /**
     * @return array<string, int>
     */
    public function dashboardCounts(): array
    {
        return [
            'users' => User::query()->count('*'),
            'roles' => Role::query()->count('*'),
            'settings' => SystemSetting::query()->count('*'),
            'sms_gateways' => SmsGateway::query()->count('*'),
            'payment_gateways' => PaymentGateway::query()->count('*'),
            'plans' => Plan::query()->count('*'),
            'open_invoices' => Invoice::query()
                ->where(fn ($query) => $query
                    ->where('status', 'pending')
                    ->orWhere('status', 'failed'))
                ->count('*'),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function managementMetrics(): array
    {
        return [
            'users' => User::query()->count('*'),
            'leads' => Lead::query()->count('*'),
            'paid_revenue' => Invoice::query()->where('status', 'paid')->sum('amount'),
            'payments' => Payment::query()->count('*'),
            'active_subscriptions' => Subscription::query()->where('status', 'active')->count('*'),
            'sms_gateways' => SmsGateway::query()->count('*'),
        ];
    }
}
