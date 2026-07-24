<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the verified visitor lead from session.
 *
 * @since 1.0.0
 */
class LeadSessionService
{
    /**
     * Return the verified lead for the current tenant session.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $message  The message value.
     * @return Lead Resolved operation result.
     *
     * @throws HttpException
     *
     * @since 1.0.0
     */
    public function verifiedLead(Request $request, Tenant $tenant, string $message): Lead
    {
        $scope = 'public.lead_session.verified_lead';

        $leadId = $request->session()->get('lead_id');

        if (! $leadId) {
            abort(Response::HTTP_FORBIDDEN, $message);
        }

        $lead = Lead::query()
            ->where('id', '=', (int) $leadId)
            ->first();

        if (! $lead) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ((int) $lead->tenant_id !== (int) $tenant->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $lead->phone_verified_at) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $lead;
    }
}
