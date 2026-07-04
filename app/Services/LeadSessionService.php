<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;
use App\Support\DebugStepLogger;
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
     * @throws HttpException
     */
    public function verifiedLead(Request $request, Tenant $tenant, string $message): Lead
    {
        $scope = 'public.lead_session.verified_lead';

        DebugStepLogger::step($request, $scope, 'Request received', [
            'tenant_id' => $tenant->id,
            'has_lead_session' => $request->session()->has('lead_id'),
        ]);

        $leadId = $request->session()->get('lead_id');

        if (! $leadId) {
            DebugStepLogger::step($request, $scope, 'Lead session missing', [
                'tenant_id' => $tenant->id,
            ]);

            abort(Response::HTTP_FORBIDDEN, $message);
        }

        DebugStepLogger::step($request, $scope, 'Lead session found', [
            'tenant_id' => $tenant->id,
            'lead_id' => $leadId,
        ]);

        $lead = Lead::query()
            ->where('id', '=', $leadId)
            ->first();

        DebugStepLogger::step($request, $scope, 'Lead lookup completed', [
            'tenant_id' => $tenant->id,
            'lead_id' => $leadId,
            'lead_found' => $lead !== null,
            'lead_tenant_id' => $lead?->tenant_id,
            'phone_verified' => $lead?->phone_verified_at !== null,
        ]);

        if (! $lead) {
            DebugStepLogger::step($request, $scope, 'Lead not found', [
                'tenant_id' => $tenant->id,
                'lead_id' => $leadId,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        if ($lead->tenant_id !== $tenant->id) {
            DebugStepLogger::step($request, $scope, 'Lead tenant mismatch', [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'lead_tenant_id' => $lead->tenant_id,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $lead->phone_verified_at) {
            DebugStepLogger::step($request, $scope, 'Lead phone is not verified', [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Verified lead resolved', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
        ]);

        return $lead;
    }
}