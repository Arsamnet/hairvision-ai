<?php

namespace App\Services\Analytics;

use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantLandingEvent;
use App\Models\TenantLandingPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Persists tenant-scoped first-party landing events and links them to verified leads.
 *
 * @since 2.1.0
 */
class LandingAnalyticsService
{
    /**
     * Record an idempotent public funnel event without accepting tenant authority or PII.
     *
     * @param  Request  $request  Current public request and verified-lead session, when present.
     * @param  Tenant  $tenant  Tenant resolved exclusively from the request host.
     * @param  array<string, mixed>  $data  Validated event identifiers.
     * @return TenantLandingEvent|null Persisted event, or null for an invalid visit token.
     *
     * @since 2.1.0
     */
    public function record(Request $request, Tenant $tenant, array $data): ?TenantLandingEvent
    {
        $tokenHash = hash('sha256', (string) $data['visit_token']);
        $visit = TenantLandingPageVisit::query()
            ->where('tenant_id', $tenant->id)
            ->where('analytics_token_hash', $tokenHash)
            ->first();

        if (! $visit) {
            return null;
        }

        $lead = $this->verifiedSessionLead($request, $tenant);
        $category = $this->category($data['category_id'] ?? null);
        $output = $this->output($data['output_id'] ?? null, $category);

        return DB::transaction(function () use ($tenant, $visit, $lead, $category, $output, $data): TenantLandingEvent {
            if ($lead && $visit->lead_id !== $lead->id) {
                $visit->update(['lead_id' => $lead->id]);
                TenantLandingEvent::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('tenant_landing_page_visit_id', $visit->id)
                    ->whereNull('lead_id')
                    ->update(['lead_id' => $lead->id]);
            }

            return TenantLandingEvent::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'event_id' => (string) $data['event_id'],
                ],
                [
                    'tenant_landing_page_visit_id' => $visit->id,
                    'lead_id' => $lead?->id,
                    'ai_model_category_id' => $category?->id,
                    'ai_model_category_output_id' => $output?->id,
                    'event_type' => (string) $data['event_type'],
                    'category_code' => $category?->code,
                    'category_name' => $category?->name,
                    'output_code' => $output?->code,
                    'occurred_at' => now(),
                ]
            );
        });
    }

    /**
     * Resolve the current verified lead only inside the resolved tenant.
     *
     * @param  Request  $request  Current public request.
     * @param  Tenant  $tenant  Host-resolved tenant.
     * @return Lead|null Verified tenant lead, when the session is valid.
     *
     * @since 2.1.0
     */
    private function verifiedSessionLead(Request $request, Tenant $tenant): ?Lead
    {
        $leadId = $request->session()->get('lead_id');

        if (! is_numeric($leadId)) {
            return null;
        }

        return Lead::query()
            ->whereKey((int) $leadId)
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('phone_verified_at')
            ->first();
    }

    /**
     * Resolve an optional system category from its public UUID.
     *
     * @param  mixed  $uuid  Submitted category UUID.
     * @return AiModelCategory|null Matching category.
     *
     * @since 2.1.0
     */
    private function category(mixed $uuid): ?AiModelCategory
    {
        return is_string($uuid) && $uuid !== ''
            ? AiModelCategory::query()->where('uuid', $uuid)->first()
            : null;
    }

    /**
     * Resolve an optional output only when it belongs to the submitted category.
     *
     * @param  mixed  $uuid  Submitted output UUID.
     * @param  AiModelCategory|null  $category  Resolved parent category.
     * @return AiModelCategoryOutput|null Matching output.
     *
     * @since 2.1.0
     */
    private function output(mixed $uuid, ?AiModelCategory $category): ?AiModelCategoryOutput
    {
        if (! is_string($uuid) || $uuid === '' || ! $category) {
            return null;
        }

        return AiModelCategoryOutput::query()
            ->where('uuid', $uuid)
            ->where('ai_model_category_id', $category->id)
            ->first();
    }
}
