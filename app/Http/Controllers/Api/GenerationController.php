<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGenerationRequest;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Upload;
use App\Services\Generation\GenerationStageCatalog;
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
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages
    ): JsonResponse {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $upload = Upload::query()
            ->where('uuid', '=', $request->post('upload_id'))
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $group = $generationService->createForUpload($upload);
        $group->refresh()->load('jobs.asset');

        return response()->json([
            'success' => true,
            'generation_group' => ['id' => $group->uuid, 'status' => $group->status],
            'current_stage' => $this->currentStage($group, $stages),
            'stages' => $stages->publicStages(),
            'jobs' => $this->jobPayload($group, $stages),
        ]);
    }

    /**
     * Return tenant- and lead-scoped generation status.
     */
    public function status(
        Request $request,
        string $groupUuid,
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages
    ): JsonResponse {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('jobs.asset')
            ->firstOrFail();

        return response()->json([
            'status' => $group->status,
            'current_stage' => $this->currentStage($group, $stages),
            'stages' => $stages->publicStages(),
            'jobs' => $this->jobPayload($group, $stages),
        ]);
    }

    /**
     * Return generated result asset URLs for a tenant- and lead-scoped group.
     */
    public function results(
        Request $request,
        string $groupUuid,
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages
    ): JsonResponse {
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
            'results' => collect($stages->sortByStageOrder($group->assets))->map(fn ($asset) => [
                'period' => $asset->period,
                'label' => $stages->label($asset->period),
                'caption' => $stages->caption($asset->period),
                'url' => '/api/v1/assets/'.$asset->uuid,
            ]),
            'disclaimer' => __('messages.result_disclaimer'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jobPayload(GenerationGroup $group, GenerationStageCatalog $stages): array
    {
        return collect($stages->sortByStageOrder($group->jobs))
            ->map(fn (GenerationJob $job): array => [
                'id' => $job->uuid,
                'period' => $job->period,
                'key' => $job->period,
                'label' => $stages->label($job->period),
                'caption' => $stages->caption($job->period),
                'status' => $job->status,
                'error' => $job->status === 'failed' ? $job->last_error : null,
                'asset_url' => $job->asset ? '/api/v1/assets/'.$job->asset->uuid : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentStage(GenerationGroup $group, GenerationStageCatalog $stages): ?array
    {
        $job = collect($stages->sortByStageOrder($group->jobs))
            ->first(fn (GenerationJob $job): bool => in_array($job->status, ['processing', 'pending', 'failed'], true));

        if (! $job instanceof GenerationJob) {
            return null;
        }

        return [
            'key' => $job->period,
            'label' => $stages->label($job->period),
            'status' => $job->status,
        ];
    }
}
