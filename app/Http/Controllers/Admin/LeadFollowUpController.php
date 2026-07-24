<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leads\StoreFollowUpRequest;
use App\Http\Requests\Admin\Leads\UpdateFollowUpRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\Admin\LeadCrmService;
use Illuminate\Http\RedirectResponse;

/**
 * Stores and updates CRM follow-up entries for a lead.
 *
 * @since 1.0.0
 */
class LeadFollowUpController extends Controller
{
    /**
     * Persist a new lead follow up record.
     *
     * @param  StoreFollowUpRequest  $request  Incoming HTTP request.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  LeadCrmService  $crm  The crm value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function store(StoreFollowUpRequest $request, Lead $lead, LeadCrmService $crm): RedirectResponse
    {
        $crm->createFollowUp($lead, $request->user(), $request->validated());

        return back()->with('status', __('crm.created'));
    }

    /**
     * Update the requested lead follow up record.
     *
     * @param  UpdateFollowUpRequest  $request  Incoming HTTP request.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  LeadNote  $leadNote  The lead note value.
     * @param  LeadCrmService  $crm  The crm value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function update(UpdateFollowUpRequest $request, Lead $lead, LeadNote $leadNote, LeadCrmService $crm): RedirectResponse
    {
        abort_unless($leadNote->lead_id === $lead->id && $leadNote->tenant_id === $lead->tenant_id, 404);

        $crm->updateFollowUp($lead, $leadNote, $request->validated());

        return back()->with('status', __('crm.updated'));
    }
}
