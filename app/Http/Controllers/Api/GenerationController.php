<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGenerationRequest;
use App\Models\GenerationGroup;
use App\Models\Upload;
use App\Services\GenerationService;
use App\Services\LeadSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coordinates tenant- and lead-scoped generation groups, status, and results.
 *
 * Controllers remain provider-agnostic; provider selection and job processing
 * are delegated to services and queued jobs.
 *
 * @since 1.0.0
 */
class GenerationController extends Controller
{
    /**
     * Create three sample-generation jobs for a verified visitor upload.
     *
     * @param  StoreGenerationRequest  $request  Validated generation request.
     * @param  GenerationService  $generationService  Service that creates jobs and dispatches processing.
     * @param  LeadSessionService  $leadSessionService  Resolves the verified session lead.
     */
    public function store(
        StoreGenerationRequest $request,
        GenerationService $generationService,
        LeadSessionService $leadSessionService
    ): JsonResponse {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $upload = Upload::query()
            ->where('uuid', '=', $request->post('upload_id'))
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $group = $generationService->createForUpload($upload);
        $group->refresh()->load('jobs');

        return response()->json([
            'success' => true,
            'generation_group' => ['id' => $group->uuid, 'status' => $group->status],
            'jobs' => $group->jobs->map(fn ($job) => ['id' => $job->uuid, 'period' => $job->period, 'status' => $job->status]),
        ]);
    }

    /**
     * Return tenant- and lead-scoped generation status.
     */
    public function status(Request $request, string $groupUuid, LeadSessionService $leadSessionService): JsonResponse
    {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('jobs')
            ->firstOrFail();

        return response()->json([
            'status' => $group->status,
            'jobs' => $group->jobs->map(fn ($job) => ['period' => $job->period, 'status' => $job->status]),
        ]);
    }

    /**
     * Return generated result asset URLs for a tenant- and lead-scoped group.
     */
    public function results(Request $request, string $groupUuid, LeadSessionService $leadSessionService): JsonResponse
    {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('assets')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'results' => $group->assets->map(fn ($asset) => [
                'period' => $asset->period,
                'url' => '/api/v1/assets/'.$asset->uuid,
            ]),
            'disclaimer' => __('messages.result_disclaimer'),
        ]);
    }
}
