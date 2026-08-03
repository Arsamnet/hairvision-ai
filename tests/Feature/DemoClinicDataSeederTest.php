<?php

namespace Tests\Feature;

use App\Models\LeadNote;
use App\Models\Tenant;
use App\Models\TenantLandingEvent;
use App\Models\TenantLandingPageVisit;
use App\Services\Analytics\ClinicPerformanceReportService;
use App\Support\Analytics\LandingEventCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoClinicDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that the demo clinic presents a complete, repeatable lead funnel.
 *
 * @since 2.1.0
 */
class DemoClinicDataSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify demo lead statuses, analytics trends, and idempotency.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_demo_clinic_seeder_builds_complete_idempotent_showcase_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        $counts = [
            'leads' => $tenant->leads()->count(),
            'notes' => LeadNote::query()->where('tenant_id', $tenant->id)->count(),
            'visits' => TenantLandingPageVisit::query()->where('tenant_id', $tenant->id)->count(),
            'events' => TenantLandingEvent::query()->where('tenant_id', $tenant->id)->count(),
        ];

        $this->assertGreaterThanOrEqual(100, $counts['leads']);
        $this->assertSame($counts['leads'], $counts['notes']);
        $this->assertGreaterThan($counts['leads'], $counts['visits']);
        $this->assertGreaterThan($counts['visits'], $counts['events']);
        $this->assertSame(
            collect(LandingEventCatalog::all())->sort()->values()->all(),
            TenantLandingEvent::query()
                ->where('tenant_id', $tenant->id)
                ->distinct()
                ->pluck('event_type')
                ->sort()
                ->values()
                ->all(),
        );
        $this->assertSame(
            collect(array_keys(__('leads.statuses')))->sort()->values()->all(),
            $tenant->leads()->distinct()->pluck('status')->sort()->values()->all(),
        );

        $service = app(ClinicPerformanceReportService::class);
        $report = $service->report(
            $tenant,
            CarbonImmutable::now()->subMonths(11)->startOfMonth(),
            CarbonImmutable::now()->endOfDay(),
        );
        $funnelCounts = collect($report['funnel'])->pluck('count')->all();

        $this->assertCount(12, $report['monthly']);
        $this->assertCount(2, $report['applications']);
        $this->assertGreaterThan(0, $report['totals']['overall_conversion_rate']);
        $this->assertSame($funnelCounts, collect($funnelCounts)->sortDesc()->values()->all());

        foreach ($report['monthly'] as $month) {
            $this->assertMatchesRegularExpression('/^1[34]\d{2}\/(0[1-9]|1[0-2])$/', $month['month']);
            $this->assertDoesNotMatchRegularExpression('/^20\d{2}\//', $month['month']);
        }

        $recent = $service->recentDailySummary($tenant);
        $this->assertCount(7, $recent['daily']);
        $this->assertNotContains(0, collect($recent['daily'])->pluck('landing_views')->all());
        $this->assertGreaterThan($recent['totals']['generation_requested'], $recent['totals']['landing_views']);

        $this->seed(DemoClinicDataSeeder::class);

        $this->assertSame($counts['leads'], $tenant->leads()->count());
        $this->assertSame($counts['notes'], LeadNote::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame($counts['visits'], TenantLandingPageVisit::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame($counts['events'], TenantLandingEvent::query()->where('tenant_id', $tenant->id)->count());
    }
}
