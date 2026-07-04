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
            'session_driver' => config('session.driver'),
            'session_cookie' => config('session.cookie'),
            'session_id' => $request->session()->getId(),
            'has_session_cookie' => $request->cookies->has(config('session.cookie')),
        ]);

        $leadId = $request->session()->get('lead_id');

        if (! $leadId) {
            DebugStepLogger::step($request, $scope, 'Lead session missing', [
                'tenant_id' => $tenant->id,
                'session_keys' => array_keys($request->session()->all()),
                'cookies' => array_keys($request->cookies->all()),
            ]);

            abort(Response::HTTP_FORBIDDEN, $message);
        }

        DebugStepLogger::step($request, $scope, 'Lead session found', [
            'tenant_id' => $tenant->id,
            'lead_id' => $leadId,
        ]);

        $lead = Lead::query()
            ->where('id', '=', (int) $leadId)
            ->first();

        DebugStepLogger::step($request, $scope, 'Lead lookup completed', [
            'tenant_id' => $tenant->id,
            'lead_id' => $leadId,
            'lead_found' => $lead !== null,
            'lead_tenant_id' => $lead?->tenant_id,
            'lead_tenant_id_type' => $lead ? gettype($lead->tenant_id) : null,
            'tenant_id_type' => gettype($tenant->id),
            'phone_verified' => $lead?->phone_verified_at !== null,
        ]);

        if (! $lead) {
            DebugStepLogger::step($request, $scope, 'Lead not found', [
                'tenant_id' => $tenant->id,
                'lead_id' => $leadId,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        if ((int) $lead->tenant_id !== (int) $tenant->id) {
            DebugStepLogger::step($request, $scope, 'Lead tenant mismatch', [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'lead_tenant_id' => $lead->tenant_id,
                'lead_tenant_id_type' => gettype($lead->tenant_id),
                'tenant_id_type' => gettype($tenant->id),
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $lead->phone_verified_at) {
            DebugStepLogger::step($request, $scope, 'Lead phone is not verified', [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
            ]);

            DebugStepLogger::step($request, $scope, 'Verification failed before upload', [
                'host' => $request->getHost(),
                'tenant_id' => $tenant->id,
                'session_driver' => config('session.driver'),
                'session_cookie' => config('session.cookie'),
                'session_id' => $request->session()->getId(),
                'has_session_cookie' => $request->cookies->has(config('session.cookie')),
                'cookies' => array_keys($request->cookies->all()),
                'session_keys' => array_keys($request->session()->all()),
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