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
use App\Models\Tenant;
use App\Models\User;
use App\Support\Analytics\LandingEventCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds reusable platform dashboard and management report metrics.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class SystemOverviewService
{
    /**
     * Execute the dashboard counts operation.
     *
     * @return array<string, int>
     *
     * @since 1.0.0
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
     * Execute the management metrics operation.
     *
     * @return array<string, int|float>
     *
     * @since 1.0.0
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

    /**
     * Build an all-time landing, lead, and final-conversion summary for every clinic.
     *
     * @return Collection<int, array<string, int|float|string>> Clinic performance rows.
     *
     * @since 2.1.0
     */
    public function clinicPerformanceSummary(): Collection
    {
        $convertedVisits = DB::table('tenant_landing_events')
            ->where('event_type', LandingEventCatalog::GENERATION_REQUESTED)
            ->select('tenant_id')
            ->selectRaw('COUNT(DISTINCT tenant_landing_page_visit_id) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        return Tenant::query()
            ->withCount(['landingPageVisits', 'leads'])
            ->orderBy('name')
            ->get()
            ->map(static function ($tenant) use ($convertedVisits): array {
                $visits = (int) $tenant->landing_page_visits_count;
                $conversions = (int) ($convertedVisits[$tenant->id] ?? 0);

                return [
                    'id' => (int) $tenant->id,
                    'name' => (string) $tenant->name,
                    'visits' => $visits,
                    'leads' => (int) $tenant->leads_count,
                    'conversions' => $conversions,
                    'conversion_rate' => $visits > 0 ? round(min(100, ($conversions / $visits) * 100), 1) : 0.0,
                ];
            });
    }
}
