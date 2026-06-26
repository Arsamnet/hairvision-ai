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
    public function store(StoreFollowUpRequest $request, Lead $lead, LeadCrmService $crm): RedirectResponse
    {
        $crm->createFollowUp($lead, $request->user(), $request->validated());

        return back()->with('status', __('crm.created'));
    }

    public function update(UpdateFollowUpRequest $request, Lead $lead, LeadNote $leadNote, LeadCrmService $crm): RedirectResponse
    {
        abort_unless($leadNote->lead_id === $lead->id && $leadNote->tenant_id === $lead->tenant_id, 404);

        $crm->updateFollowUp($lead, $leadNote, $request->validated());

        return back()->with('status', __('crm.updated'));
    }
}
