<?php

namespace App\Services\Analytics;

use App\Models\Tenant;
use App\Support\Analytics\LandingEventCatalog;
use App\Support\Analytics\LandingVisitorIdentity;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

/**
 * Builds cohort-based tenant landing funnels, monthly trends, and application click reports.
 *
 * @since 2.1.0
 * @since 2.1.1 Defines final conversion as unique landing visitors converted to unique leads.
 */
class ClinicPerformanceReportService
{
    /**
     * Build a compact daily acquisition summary for the clinic dashboard.
     *
     * @param  Tenant  $tenant  Authorized dashboard tenant.
     * @param  int  $days  Number of trailing calendar days to include.
     * @return array{totals: array{landing_views: int, unique_visitors: int, leads: int, otp_verified: int, generation_requested: int, conversion_rate: float}, daily: array<int, array{date: string, label: string, landing_views: int, unique_visitors: int, leads: int, otp_verified: int, generation_requested: int}>, chart_max: int} Recent performance summary.
     *
     * @since 2.1.0
     * @since 2.1.1 Uses unique visitors and linked leads for the final conversion KPI.
     */
    public function recentDailySummary(Tenant $tenant, int $days = 7): array
    {
        $days = max(1, min(31, $days));
        $until = CarbonImmutable::now()->endOfDay();
        $from = $until->subDays($days - 1)->startOfDay();
        $daily = [];
        $dailyVisitorKeys = [];
        $dailyLeadIds = [];

        for ($cursor = $from; $cursor->lte($until); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $jalaliDate = (string) JalaliDate::toJalaliDate($cursor);
            $daily[$date] = [
                'date' => $date,
                'label' => substr($jalaliDate, 5),
                'landing_views' => 0,
                'unique_visitors' => 0,
                'leads' => 0,
                'otp_verified' => 0,
                'generation_requested' => 0,
            ];
            $dailyVisitorKeys[$date] = [];
            $dailyLeadIds[$date] = [];
        }

        $visitRows = $this->visitCohortRows($tenant, $from, $until);

        foreach ($visitRows as $row) {
            $date = CarbonImmutable::parse((string) $row->visited_at)->toDateString();

            if (isset($daily[$date])) {
                $daily[$date]['landing_views']++;
                $dailyVisitorKeys[$date][LandingVisitorIdentity::key($row)] = true;

                if ($row->lead_id !== null) {
                    $dailyLeadIds[$date][(int) $row->lead_id] = true;
                }
            }
        }

        $eventRows = $this->eventCohortQuery($tenant, $from, $until)
            ->whereIn('events.event_type', [
                LandingEventCatalog::OTP_VERIFIED,
                LandingEventCatalog::GENERATION_REQUESTED,
            ])
            ->selectRaw('DATE(visits.visited_at) as visit_day, events.event_type')
            ->selectRaw('COUNT(DISTINCT events.tenant_landing_page_visit_id) as visitors')
            ->groupByRaw('DATE(visits.visited_at), events.event_type')
            ->get();

        foreach ($eventRows as $row) {
            $date = (string) $row->visit_day;
            $eventType = (string) $row->event_type;

            if (isset($daily[$date]) && array_key_exists($eventType, $daily[$date])) {
                $daily[$date][$eventType] = (int) $row->visitors;
            }
        }

        foreach ($daily as $date => &$row) {
            $row['unique_visitors'] = count($dailyVisitorKeys[$date]);
            $row['leads'] = count($dailyLeadIds[$date]);
        }
        unset($row);

        $rows = array_values($daily);
        $visitorLeadMetrics = $this->visitorLeadMetrics($visitRows);
        $landingViews = array_sum(array_column($rows, 'landing_views'));
        $otpVerified = array_sum(array_column($rows, LandingEventCatalog::OTP_VERIFIED));
        $generationRequested = array_sum(array_column($rows, LandingEventCatalog::GENERATION_REQUESTED));

        return [
            'totals' => [
                'landing_views' => $landingViews,
                'unique_visitors' => $visitorLeadMetrics['unique_visitors'],
                'leads' => $visitorLeadMetrics['leads'],
                'otp_verified' => $otpVerified,
                'generation_requested' => $generationRequested,
                'conversion_rate' => $this->percentage(
                    $visitorLeadMetrics['leads'],
                    $visitorLeadMetrics['unique_visitors'],
                ),
            ],
            'daily' => $rows,
            'chart_max' => max(1, ...array_column($rows, 'landing_views')),
        ];
    }

    /**
     * Resolve a validated Jalali range or the default trailing twelve-month window.
     *
     * @param  array<string, mixed>  $filters  Validated report filters.
     * @return array{from: CarbonImmutable, until: CarbonImmutable, date_from: string, date_until: string} Resolved range.
     *
     * @since 2.1.0
     */
    public function range(array $filters): array
    {
        $until = filled($filters['date_until'] ?? null)
            ? CarbonImmutable::instance(JalaliDate::fromJalaliDate((string) $filters['date_until']))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $from = filled($filters['date_from'] ?? null)
            ? CarbonImmutable::instance(JalaliDate::fromJalaliDate((string) $filters['date_from']))->startOfDay()
            : $until->subMonths(11)->startOfMonth()->startOfDay();

        return [
            'from' => $from,
            'until' => $until,
            'date_from' => (string) JalaliDate::toJalaliDate($from),
            'date_until' => (string) JalaliDate::toJalaliDate($until),
        ];
    }

    /**
     * Build quick report ranges aligned to complete Jalali calendar months.
     *
     * The current-month shortcut ends today so it never requests future dates;
     * the previous-month shortcut covers that full Jalali month.
     *
     * @param  CarbonImmutable|null  $now  Optional current time for deterministic callers and tests.
     * @return array{current_month: array{date_from: string, date_until: string}, previous_month: array{date_from: string, date_until: string}} Jalali shortcut query values.
     *
     * @since 2.1.0
     */
    public function quickRanges(?CarbonImmutable $now = null): array
    {
        $today = Jalalian::fromDateTime($now ?? CarbonImmutable::now());
        $currentMonthStart = $today->getFirstDayOfMonth();
        $previousMonthStart = $currentMonthStart->subMonths();

        return [
            'current_month' => [
                'date_from' => $currentMonthStart->format(JalaliDate::DATE_FORMAT),
                'date_until' => $today->format(JalaliDate::DATE_FORMAT),
            ],
            'previous_month' => [
                'date_from' => $previousMonthStart->format(JalaliDate::DATE_FORMAT),
                'date_until' => $previousMonthStart->getEndDayOfMonth()->format(JalaliDate::DATE_FORMAT),
            ],
        ];
    }

    /**
     * Build the full first-party performance report for one authorized tenant.
     *
     * Events are attributed to the month in which their landing visit began, so
     * conversion rates describe a stable acquisition cohort and cannot exceed
     * page views because a later event crossed a reporting boundary.
     *
     * @param  Tenant  $tenant  Authorized dashboard tenant.
     * @param  CarbonImmutable  $from  Inclusive visit start.
     * @param  CarbonImmutable  $until  Inclusive visit end.
     * @return array<string, mixed> Report metrics and presentation rows.
     *
     * @since 2.1.0
     */
    public function report(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->buildReport($tenant, $from, $until);
    }

    /**
     * Build an authorized system-wide performance report across every clinic.
     *
     * This method intentionally has no tenant condition and must only be called
     * after the controller has verified system-scoped report access.
     *
     * @param  CarbonImmutable  $from  Inclusive visit start.
     * @param  CarbonImmutable  $until  Inclusive visit end.
     * @return array<string, mixed> Aggregated report metrics and presentation rows.
     *
     * @since 2.1.0
     */
    public function systemReport(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->buildReport(null, $from, $until);
    }

    /**
     * Build tenant-specific or system-wide report data from one query contract.
     *
     * @param  Tenant|null  $tenant  Tenant scope, or null for an authorized system aggregate.
     * @param  CarbonImmutable  $from  Inclusive visit start.
     * @param  CarbonImmutable  $until  Inclusive visit end.
     * @return array<string, mixed> Report metrics and presentation rows.
     *
     * @since 2.1.0
     * @since 2.1.1 Adds unique-visitor and unique-lead totals and redefines final conversion.
     */
    private function buildReport(?Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $visitRows = $this->visitCohortRows($tenant, $from, $until);
        $visits = $visitRows->count();
        $visitorLeadMetrics = $this->visitorLeadMetrics($visitRows);
        $eventCounts = $this->uniqueEventCounts($tenant, $from, $until);
        $applicationClicks = $this->applicationClickCount($tenant, $from, $until);
        $funnel = $this->funnel($visits, $eventCounts);
        $monthly = $this->monthly($tenant, $from, $until, $visitRows);
        $leadQuery = DB::table('leads')->whereBetween('created_at', [$from, $until]);

        if ($tenant) {
            $leadQuery->where('tenant_id', $tenant->id);
        }

        return [
            'totals' => [
                'landing_views' => $visits,
                'unique_visitors' => $visitorLeadMetrics['unique_visitors'],
                'total_leads' => $visitorLeadMetrics['leads'],
                'application_clicks' => $applicationClicks,
                'application_visitors' => (int) ($eventCounts[LandingEventCatalog::APPLICATION_CLICKED] ?? 0),
                'photo_selected' => (int) ($eventCounts[LandingEventCatalog::PHOTO_SELECTED] ?? 0),
                'phone_submitted' => (int) ($eventCounts[LandingEventCatalog::PHONE_SUBMITTED] ?? 0),
                'otp_verified' => (int) ($eventCounts[LandingEventCatalog::OTP_VERIFIED] ?? 0),
                'photo_uploaded' => (int) ($eventCounts[LandingEventCatalog::PHOTO_UPLOADED] ?? 0),
                'generation_requested' => (int) ($eventCounts[LandingEventCatalog::GENERATION_REQUESTED] ?? 0),
                'result_viewed' => (int) ($eventCounts[LandingEventCatalog::RESULT_VIEWED] ?? 0),
                'lead_conversion_rate' => $this->percentage(
                    $visitorLeadMetrics['leads'],
                    $visitorLeadMetrics['unique_visitors'],
                ),
                'overall_conversion_rate' => $this->percentage(
                    $visitorLeadMetrics['leads'],
                    $visitorLeadMetrics['unique_visitors'],
                ),
            ],
            'funnel' => $funnel,
            'monthly' => $monthly,
            'applications' => $this->applicationRows($tenant, $from, $until),
            'statuses' => $leadQuery
                ->select('status')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(static fn (mixed $count): int => (int) $count)
                ->all(),
            'chart_max' => max(1, ...array_map(
                static fn (array $row): int => max(
                    (int) $row['landing_views'],
                    (int) $row['phone_submitted'],
                    (int) $row['photo_uploaded'],
                    (int) $row['generation_requested'],
                ),
                $monthly,
            )),
        ];
    }

    /**
     * Count distinct visit cohorts that reached each event stage.
     *
     * @param  Tenant|null  $tenant  Tenant report scope, or null for a system aggregate.
     * @param  CarbonImmutable  $from  Inclusive range start.
     * @param  CarbonImmutable  $until  Inclusive range end.
     * @return array<string, int> Counts keyed by event type.
     *
     * @since 2.1.0
     */
    private function uniqueEventCounts(?Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->eventCohortQuery($tenant, $from, $until)
            ->select('events.event_type')
            ->selectRaw('COUNT(DISTINCT events.tenant_landing_page_visit_id) as aggregate')
            ->groupBy('events.event_type')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->event_type => (int) $row->aggregate])
            ->all();
    }

    /**
     * Count every application-button click in the selected visit cohort.
     *
     * @param  Tenant|null  $tenant  Tenant report scope, or null for a system aggregate.
     * @param  CarbonImmutable  $from  Inclusive range start.
     * @param  CarbonImmutable  $until  Inclusive range end.
     * @return int Total click events.
     *
     * @since 2.1.0
     */
    private function applicationClickCount(?Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): int
    {
        return $this->eventCohortQuery($tenant, $from, $until)
            ->where('events.event_type', LandingEventCatalog::APPLICATION_CLICKED)
            ->count();
    }

    /**
     * Build ordered funnel rows and both step-to-step and landing conversion rates.
     *
     * @param  int  $visits  Landing page views.
     * @param  array<string, int>  $eventCounts  Distinct visitor counts by event.
     * @return array<int, array<string, int|float|string>> Funnel rows.
     *
     * @since 2.1.0
     */
    private function funnel(int $visits, array $eventCounts): array
    {
        $rows = [[
            'key' => 'landing_view',
            'count' => $visits,
            'from_previous_rate' => $visits > 0 ? 100.0 : 0.0,
            'from_landing_rate' => $visits > 0 ? 100.0 : 0.0,
        ]];
        $previous = $visits;

        foreach (LandingEventCatalog::funnel() as $eventType) {
            $count = (int) ($eventCounts[$eventType] ?? 0);
            $rows[] = [
                'key' => $eventType,
                'count' => $count,
                'from_previous_rate' => $this->percentage($count, $previous),
                'from_landing_rate' => $this->percentage($count, $visits),
            ];
            $previous = $count;
        }

        return $rows;
    }

    /**
     * Aggregate visits and every funnel stage by Jalali acquisition month.
     *
     * @param  Tenant|null  $tenant  Tenant report scope, or null for a system aggregate.
     * @param  CarbonImmutable  $from  Inclusive range start.
     * @param  CarbonImmutable  $until  Inclusive range end.
     * @param  Collection<int, object>  $visitRows  Landing visits already scoped to this report cohort.
     * @return array<int, array<string, int|float|string>> Monthly report rows.
     *
     * @since 2.1.0
     * @since 2.1.1 Calculates each month's final conversion from unique visitors to unique leads.
     */
    private function monthly(
        ?Tenant $tenant,
        CarbonImmutable $from,
        CarbonImmutable $until,
        Collection $visitRows,
    ): array {
        $months = [];
        $monthVisitorKeys = [];
        $monthLeadIds = [];
        $cursor = $from->startOfDay();

        // A 20-day stride cannot skip a Jalali month (the shortest has 29 days).
        while ($cursor->lte($until)) {
            $month = $this->monthKey($cursor->toDateString());
            $months[$month] ??= $this->emptyMonth($month);
            $monthVisitorKeys[$month] ??= [];
            $monthLeadIds[$month] ??= [];
            $cursor = $cursor->addDays(20);
        }

        $untilMonth = $this->monthKey($until->toDateString());
        $months[$untilMonth] ??= $this->emptyMonth($untilMonth);
        $monthVisitorKeys[$untilMonth] ??= [];
        $monthLeadIds[$untilMonth] ??= [];

        foreach ($visitRows as $row) {
            $month = $this->monthKey(CarbonImmutable::parse((string) $row->visited_at)->toDateString());
            $months[$month] ??= $this->emptyMonth($month);
            $monthVisitorKeys[$month] ??= [];
            $monthLeadIds[$month] ??= [];
            $months[$month]['landing_views']++;
            $monthVisitorKeys[$month][LandingVisitorIdentity::key($row)] = true;

            if ($row->lead_id !== null) {
                $monthLeadIds[$month][(int) $row->lead_id] = true;
            }
        }

        $eventRows = $this->eventCohortQuery($tenant, $from, $until)
            ->selectRaw('DATE(visits.visited_at) as visit_day, events.event_type')
            ->selectRaw('COUNT(*) as event_total')
            ->selectRaw('COUNT(DISTINCT events.tenant_landing_page_visit_id) as visitors')
            ->groupByRaw('DATE(visits.visited_at), events.event_type')
            ->get();

        foreach ($eventRows as $row) {
            $month = $this->monthKey((string) $row->visit_day);
            $months[$month] ??= $this->emptyMonth($month);
            $eventType = (string) $row->event_type;

            if ($eventType === LandingEventCatalog::APPLICATION_CLICKED) {
                $months[$month]['application_clicks'] += (int) $row->event_total;
            }

            if (array_key_exists($eventType, $months[$month])) {
                $months[$month][$eventType] += (int) $row->visitors;
            }
        }

        ksort($months);

        return array_values(array_map(function (array $row) use ($monthVisitorKeys, $monthLeadIds): array {
            $month = (string) $row['month'];
            $row['unique_visitors'] = count($monthVisitorKeys[$month] ?? []);
            $row['total_leads'] = count($monthLeadIds[$month] ?? []);
            $row['conversion_rate'] = $this->percentage(
                (int) $row['total_leads'],
                (int) $row['unique_visitors'],
            );

            return $row;
        }, $months));
    }

    /**
     * Build click and downstream conversion rows for each application.
     *
     * @param  Tenant|null  $tenant  Tenant report scope, or null for a system aggregate.
     * @param  CarbonImmutable  $from  Inclusive range start.
     * @param  CarbonImmutable  $until  Inclusive range end.
     * @return array<int, array<string, int|float|string>> Application rows.
     *
     * @since 2.1.0
     */
    private function applicationRows(?Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $rows = $this->eventCohortQuery($tenant, $from, $until)
            ->whereIn('events.event_type', [
                LandingEventCatalog::APPLICATION_CLICKED,
                LandingEventCatalog::GENERATION_REQUESTED,
            ])
            ->whereNotNull('events.category_code')
            ->select('events.category_code', 'events.event_type')
            ->selectRaw('MAX(events.category_name) as category_name')
            ->selectRaw('COUNT(*) as event_total')
            ->selectRaw('COUNT(DISTINCT events.tenant_landing_page_visit_id) as visitors')
            ->groupBy('events.category_code', 'events.event_type')
            ->get();
        $applications = [];

        foreach ($rows as $row) {
            $code = (string) $row->category_code;
            $applications[$code] ??= [
                'code' => $code,
                'name' => (string) ($row->category_name ?: $code),
                'clicks' => 0,
                'unique_visitors' => 0,
                'generation_visitors' => 0,
                'conversion_rate' => 0.0,
            ];

            if ($row->event_type === LandingEventCatalog::APPLICATION_CLICKED) {
                $applications[$code]['clicks'] = (int) $row->event_total;
                $applications[$code]['unique_visitors'] = (int) $row->visitors;
            } elseif ($row->event_type === LandingEventCatalog::GENERATION_REQUESTED) {
                $applications[$code]['generation_visitors'] = (int) $row->visitors;
            }
        }

        foreach ($applications as &$application) {
            $application['conversion_rate'] = $this->percentage(
                (int) $application['generation_visitors'],
                (int) $application['unique_visitors'],
            );
        }
        unset($application);

        return array_values(collect($applications)->sortByDesc('clicks')->all());
    }

    /**
     * Build the shared event query scoped by the landing visit acquisition date.
     *
     * @param  Tenant|null  $tenant  Tenant report scope, or null for a system aggregate.
     * @param  CarbonImmutable  $from  Inclusive range start.
     * @param  CarbonImmutable  $until  Inclusive range end.
     * @return Builder Scoped query builder.
     *
     * @since 2.1.0
     */
    private function eventCohortQuery(?Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        $query = DB::table('tenant_landing_events as events')
            ->join('tenant_landing_page_visits as visits', 'visits.id', '=', 'events.tenant_landing_page_visit_id')
            ->whereColumn('events.tenant_id', 'visits.tenant_id')
            ->whereBetween('visits.visited_at', [$from, $until]);

        if ($tenant) {
            $query->where('visits.tenant_id', $tenant->id);
        }

        return $query;
    }

    /**
     * Return an empty monthly metric row.
     *
     * @param  string  $month  Jalali year/month label.
     * @return array<string, int|float|string> Empty monthly metrics.
     *
     * @since 2.1.0
     */
    private function emptyMonth(string $month): array
    {
        return [
            'month' => $month,
            'landing_views' => 0,
            'unique_visitors' => 0,
            'total_leads' => 0,
            'application_clicks' => 0,
            LandingEventCatalog::APPLICATION_CLICKED => 0,
            LandingEventCatalog::PHOTO_SELECTED => 0,
            LandingEventCatalog::PHONE_SUBMITTED => 0,
            LandingEventCatalog::OTP_VERIFIED => 0,
            LandingEventCatalog::PHOTO_UPLOADED => 0,
            LandingEventCatalog::GENERATION_REQUESTED => 0,
            LandingEventCatalog::RESULT_VIEWED => 0,
            'conversion_rate' => 0.0,
        ];
    }

    /**
     * Load the minimal visit cohort used for unique visitor and lead metrics.
     *
     * @param  Tenant|null  $tenant  Tenant report scope, or null for a system aggregate.
     * @param  CarbonImmutable  $from  Inclusive range start.
     * @param  CarbonImmutable  $until  Inclusive range end.
     * @return Collection<int, object> Scoped visit identities and acquisition dates.
     *
     * @since 2.1.1
     */
    private function visitCohortRows(?Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return DB::table('tenant_landing_page_visits')
            ->select(['id', 'tenant_id', 'lead_id', 'ip_hash', 'user_agent_hash', 'visited_at'])
            ->whereBetween('visited_at', [$from, $until])
            ->when($tenant, fn (Builder $query) => $query->where('tenant_id', $tenant->id))
            ->orderBy('id')
            ->get();
    }

    /**
     * Count unique visitors and the unique leads linked to their cohort visits.
     *
     * A verified lead is the strongest visitor identity. Anonymous visits use
     * their already-hashed IP and user-agent pair, with the visit id as a safe
     * fallback when no stable anonymous fingerprint was recorded.
     *
     * @param  Collection<int, object>  $visitRows  Scoped landing visits.
     * @return array{unique_visitors: int, leads: int} Unique acquisition metrics.
     *
     * @since 2.1.1
     */
    private function visitorLeadMetrics(Collection $visitRows): array
    {
        $visitorKeys = [];
        $leadIds = [];

        foreach ($visitRows as $visit) {
            $visitorKeys[LandingVisitorIdentity::key($visit)] = true;

            if ($visit->lead_id !== null) {
                $leadIds[(int) $visit->lead_id] = true;
            }
        }

        return [
            'unique_visitors' => count($visitorKeys),
            'leads' => count($leadIds),
        ];
    }

    /**
     * Convert a Gregorian database date to a sortable Jalali year/month key.
     *
     * @param  string  $date  Gregorian date.
     * @return string Jalali year/month.
     *
     * @since 2.1.0
     */
    private function monthKey(string $date): string
    {
        return substr((string) JalaliDate::toJalaliDate(CarbonImmutable::parse($date)), 0, 7);
    }

    /**
     * Calculate a bounded percentage rounded to one decimal place.
     *
     * @param  int  $numerator  Converted visitor count.
     * @param  int  $denominator  Previous or initial visitor count.
     * @return float Conversion percentage.
     *
     * @since 2.1.0
     */
    private function percentage(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(min(100, ($numerator / $denominator) * 100), 1);
    }
}
