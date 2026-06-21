<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the verified visitor lead stored in the session.
 *
 * This service keeps public API controllers thin while preserving the MVP rule
 * that visitor resources are scoped by both the resolved tenant and session
 * lead.
 *
 * @since 1.0.0
 */
class LeadSessionService
{
    /**
     * Return the current verified lead for a tenant-scoped visitor session.
     *
     * @param  Request  $request  Request carrying the Laravel session.
     * @param  Tenant  $tenant  Tenant resolved from the request host.
     * @param  string  $message  Visitor-facing message used when no lead session exists.
     *
     * @throws HttpException When the visitor has not verified a phone number.
     */
    public function verifiedLead(Request $request, Tenant $tenant, string $message): Lead
    {
        $leadId = $request->session()->get('lead_id');

        if (! $leadId) {
            abort(403, $message);
        }

        return Lead::query()
            ->where('tenant_id', '=', $tenant->id)
            ->where('id', '=', $leadId)
            ->whereNotNull('phone_verified_at')
            ->firstOrFail();
    }
}
