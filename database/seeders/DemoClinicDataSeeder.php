<?php

namespace Database\Seeders;

use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Tenant;
use App\Models\TenantLandingEvent;
use App\Models\TenantLandingPageVisit;
use App\Models\User;
use App\Support\Analytics\LandingEventCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Seeds an idempotent, presentation-ready acquisition funnel for the demo clinic.
 *
 * @since 2.1.0
 */
class DemoClinicDataSeeder extends Seeder
{
    /** @var array<int, string> */
    private const FIRST_NAMES = [
        'آرمان', 'سارا', 'نیما', 'مهسا', 'سامان', 'الهام', 'پویا', 'نگار', 'کیان', 'بهاره',
    ];

    /** @var array<int, string> */
    private const LAST_NAMES = [
        'رضایی', 'محمدی', 'کریمی', 'احمدی', 'نادری', 'صادقی', 'شریفی', 'مرادی', 'توکلی', 'یزدانی',
    ];

    /** @var array<int, string> */
    private const LEAD_STATUSES = [
        'new',
        'needs_follow_up',
        'no_answer',
        'successful',
        'canceled',
    ];

    /** @var array<int, string> */
    private const CITIES = [
        'تهران', 'کرج', 'قم', 'رشت', 'قزوین',
    ];

    /** @var array<int, string> */
    private const SOURCES = [
        'google_search', 'instagram', 'direct', 'clinic_referral', 'website_form',
    ];

    /** @var array<int, string|null> */
    private const REFERERS = [
        'https://www.google.com', 'https://www.instagram.com', null, 'https://clinic.example',
    ];

    /**
     * Seed twelve months of trend data plus a guaranteed seven-day dashboard series.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
            $admins = User::query()
                ->whereIn('phone_normalized', ['09004666673', '09127072327'])
                ->orderBy('id')
                ->get();
            $categories = $this->demoCategories();
            $now = CarbonImmutable::now();

            for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
                $month = $now->subMonthsNoOverflow($monthOffset);
                $start = $month->startOfMonth();
                $end = $monthOffset === 0 ? $now : $month->endOfMonth();
                $visitCount = 8 + (11 - $monthOffset);

                $this->seedCohort(
                    tenant: $tenant,
                    admins: $admins,
                    categories: $categories,
                    cohortKey: 'month-'.$monthOffset,
                    phoneSequenceBase: $monthOffset * 100,
                    visitCount: $visitCount,
                    start: $start,
                    end: $end,
                );
            }

            for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
                $day = $now->subDays($dayOffset);

                $this->seedCohort(
                    tenant: $tenant,
                    admins: $admins,
                    categories: $categories,
                    cohortKey: 'recent-'.$dayOffset,
                    phoneSequenceBase: 5000 + ($dayOffset * 50),
                    visitCount: 5 + (6 - $dayOffset),
                    start: $day->startOfDay(),
                    end: $dayOffset === 0 ? $now : $day->endOfDay(),
                );
            }
        });
    }

    /**
     * Resolve the two seeded demo applications and one active output per application.
     *
     * @return Collection<int, AiModelCategory> Ordered demo categories.
     *
     * @since 2.1.0
     */
    private function demoCategories(): Collection
    {
        $categories = AiModelCategory::query()
            ->active()
            ->whereIn('code', ['hair_transplant', 'eyebrow_transplant'])
            ->with(['outputs' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id')])
            ->get()
            ->keyBy('code');

        return collect(['hair_transplant', 'eyebrow_transplant'])
            ->map(fn (string $code): AiModelCategory => $categories->get($code) ?? throw new \RuntimeException("Missing demo category [{$code}]."));
    }

    /**
     * Seed one deterministic group of visits and its non-increasing funnel stages.
     *
     * @param  Tenant  $tenant  Demo tenant.
     * @param  Collection<int, User>  $admins  Demo clinic administrators.
     * @param  Collection<int, AiModelCategory>  $categories  Demo applications.
     * @param  string  $cohortKey  Stable cohort namespace.
     * @param  int  $phoneSequenceBase  Stable phone sequence offset.
     * @param  int  $visitCount  Visits in this cohort.
     * @param  CarbonImmutable  $start  Earliest acquisition time.
     * @param  CarbonImmutable  $end  Latest acquisition time.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    private function seedCohort(
        Tenant $tenant,
        Collection $admins,
        Collection $categories,
        string $cohortKey,
        int $phoneSequenceBase,
        int $visitCount,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): void {
        $stageLimits = [
            LandingEventCatalog::APPLICATION_CLICKED => (int) ceil($visitCount * 0.86),
            LandingEventCatalog::PHOTO_SELECTED => (int) ceil($visitCount * 0.74),
            LandingEventCatalog::PHONE_SUBMITTED => (int) ceil($visitCount * 0.64),
            LandingEventCatalog::OTP_VERIFIED => (int) ceil($visitCount * 0.54),
            LandingEventCatalog::PHOTO_UPLOADED => (int) ceil($visitCount * 0.46),
            LandingEventCatalog::GENERATION_REQUESTED => (int) ceil($visitCount * 0.36),
            LandingEventCatalog::RESULT_VIEWED => (int) ceil($visitCount * 0.29),
        ];
        $spanDays = max(0, (int) $start->diffInDays($end));

        for ($visitIndex = 0; $visitIndex < $visitCount; $visitIndex++) {
            $visitKey = $cohortKey.'-'.$visitIndex;
            $dayOffset = ($visitIndex * 5 + $phoneSequenceBase) % ($spanDays + 1);
            $visitedAt = $start
                ->addDays($dayOffset)
                ->setTime(9 + ($visitIndex % 9), ($visitIndex * 7) % 60);

            if ($visitedAt->gt($end)) {
                $visitedAt = $end->subMinutes($visitIndex + 1);
            }

            $category = $categories[$visitIndex % $categories->count()];
            $output = $category->outputs->first();
            $lead = $visitIndex < $stageLimits[LandingEventCatalog::OTP_VERIFIED]
                ? $this->seedLead(
                    tenant: $tenant,
                    admins: $admins,
                    sequence: $phoneSequenceBase + $visitIndex,
                    verifiedAt: $visitedAt->addMinutes(4),
                )
                : null;
            $visit = $this->seedVisit($tenant, $visitKey, $visitIndex, $visitedAt, $lead);

            foreach (LandingEventCatalog::funnel() as $stageIndex => $eventType) {
                if ($visitIndex >= $stageLimits[$eventType]) {
                    continue;
                }

                $this->seedEvent(
                    tenant: $tenant,
                    visit: $visit,
                    lead: $lead,
                    category: $category,
                    output: $output,
                    eventKey: $visitKey.'-'.$eventType,
                    eventType: $eventType,
                    occurredAt: $visitedAt->addMinutes($stageIndex + 1),
                );
            }

            if ($visitIndex < $stageLimits[LandingEventCatalog::APPLICATION_CLICKED] && $visitIndex % 4 === 0) {
                $this->seedEvent(
                    tenant: $tenant,
                    visit: $visit,
                    lead: $lead,
                    category: $category,
                    output: $output,
                    eventKey: $visitKey.'-application-clicked-repeat',
                    eventType: LandingEventCatalog::APPLICATION_CLICKED,
                    occurredAt: $visitedAt->addSeconds(75),
                );
            }
        }
    }

    /**
     * Seed one verified demo lead and its CRM note.
     *
     * @param  Tenant  $tenant  Demo tenant.
     * @param  Collection<int, User>  $admins  Assignable demo administrators.
     * @param  int  $sequence  Stable lead number.
     * @param  CarbonImmutable  $verifiedAt  Verification timestamp.
     * @return Lead Seeded lead.
     *
     * @since 2.1.0
     */
    private function seedLead(
        Tenant $tenant,
        Collection $admins,
        int $sequence,
        CarbonImmutable $verifiedAt,
    ): Lead {
        $phone = sprintf('09129%06d', $sequence);
        $status = self::LEAD_STATUSES[$sequence % count(self::LEAD_STATUSES)];
        $admin = $admins->isEmpty() ? null : $admins[$sequence % $admins->count()];
        $lead = Lead::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'phone_normalized' => $phone],
            [
                'full_name' => self::FIRST_NAMES[$sequence % count(self::FIRST_NAMES)].' '.self::LAST_NAMES[($sequence * 3) % count(self::LAST_NAMES)],
                'age' => 20 + ($sequence % 45),
                'city' => self::CITIES[$sequence % count(self::CITIES)],
                'phone' => $phone,
                'source' => self::SOURCES[$sequence % count(self::SOURCES)],
                'phone_verified_at' => $verifiedAt,
                'status' => $status,
                'crm_last_note_at' => $verifiedAt->addMinutes(20),
                'assigned_user_id' => $admin?->id,
                'last_seen_at' => $verifiedAt->addMinutes(12),
            ],
        );

        $lead->forceFill([
            'created_at' => $verifiedAt,
            'updated_at' => $verifiedAt->addMinutes(20),
        ])->saveQuietly();

        $note = LeadNote::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'note' => 'یادداشت نمایشی مسیر پیگیری لید کلینیک دمو.',
            ],
            [
                'user_id' => $admin?->id,
                'type' => in_array($status, ['needs_follow_up', 'no_answer'], true) ? 'follow_up' : 'admin_note',
                'status' => $status,
                'communication_channel' => ['phone', 'sms', 'whatsapp', 'in_person'][$sequence % 4],
                'metadata_json' => ['seeded' => true, 'demo' => true],
            ],
        );
        $note->forceFill([
            'created_at' => $verifiedAt->addMinutes(20),
            'updated_at' => $verifiedAt->addMinutes(20),
        ])->saveQuietly();

        return $lead;
    }

    /**
     * Seed one anonymous landing visit and associate it with a verified lead when present.
     *
     * @param  Tenant  $tenant  Demo tenant.
     * @param  string  $visitKey  Stable visit namespace.
     * @param  int  $visitIndex  Visit position in cohort.
     * @param  CarbonImmutable  $visitedAt  Acquisition timestamp.
     * @param  Lead|null  $lead  Verified lead, when the visit converted.
     * @return TenantLandingPageVisit Seeded visit.
     *
     * @since 2.1.0
     */
    private function seedVisit(
        Tenant $tenant,
        string $visitKey,
        int $visitIndex,
        CarbonImmutable $visitedAt,
        ?Lead $lead,
    ): TenantLandingPageVisit {
        $tokenHash = hash('sha256', 'hairvision:demo:visit:'.$visitKey);
        $visit = TenantLandingPageVisit::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'analytics_token_hash' => $tokenHash],
            [
                'lead_id' => $lead?->id,
                'host' => $tenant->domains()->where('type', 'tenant_subdomain')->value('domain') ?: 'demo.hairvision.local',
                'ip_hash' => hash('sha256', 'hairvision:demo:ip:'.($visitIndex % 19)),
                'user_agent_hash' => hash('sha256', 'hairvision:demo:user-agent:'.($visitIndex % 7)),
                'referer' => self::REFERERS[$visitIndex % count(self::REFERERS)],
                'visited_at' => $visitedAt,
            ],
        );
        $visit->forceFill([
            'created_at' => $visitedAt,
            'updated_at' => $visitedAt,
        ])->saveQuietly();

        return $visit;
    }

    /**
     * Seed one stable, PII-free landing funnel event.
     *
     * @param  Tenant  $tenant  Demo tenant.
     * @param  TenantLandingPageVisit  $visit  Owning visit.
     * @param  Lead|null  $lead  Converted lead when available.
     * @param  AiModelCategory  $category  Selected application.
     * @param  AiModelCategoryOutput|null  $output  Selected output.
     * @param  string  $eventKey  Stable event namespace.
     * @param  string  $eventType  Allowlisted funnel event.
     * @param  CarbonImmutable  $occurredAt  Event timestamp.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    private function seedEvent(
        Tenant $tenant,
        TenantLandingPageVisit $visit,
        ?Lead $lead,
        AiModelCategory $category,
        ?AiModelCategoryOutput $output,
        string $eventKey,
        string $eventType,
        CarbonImmutable $occurredAt,
    ): void {
        $event = TenantLandingEvent::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'hairvision:demo:event:'.$eventKey)->toString(),
            ],
            [
                'tenant_landing_page_visit_id' => $visit->id,
                'lead_id' => $lead?->id,
                'ai_model_category_id' => $category->id,
                'ai_model_category_output_id' => $output?->id,
                'event_type' => $eventType,
                'category_code' => $category->code,
                'category_name' => $category->name,
                'output_code' => $output?->code,
                'occurred_at' => $occurredAt,
            ],
        );
        $event->forceFill([
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ])->saveQuietly();
    }
}
