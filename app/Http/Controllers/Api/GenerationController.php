<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGenerationRequest;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Tenant;
use App\Models\Upload;
use App\Services\Generation\GenerationStageCatalog;
use App\Services\GenerationService;
use App\Services\LeadSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles tenant- and lead-scoped generation APIs.
 *
 * @since 1.0.0
 */
class GenerationController extends Controller
{
    /**
     * Create staged generation jobs for a verified upload.
     */
    public function store(
        StoreGenerationRequest $request,
        GenerationService $generationService,
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages
    ): JsonResponse {
        $scope = 'generation.store';

        $tenant = $this->tenantFromRequest($request, $scope);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $upload = Upload::query()
            ->where('uuid', '=', $request->post('upload_id'))
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $group = $generationService->createForUpload($upload);
        $group->refresh()->load('jobs.asset');

        $response = [
            'success' => true,
            'generation_group' => [
                'id' => $group->uuid,
                'status' => $group->status,
            ],
            'current_stage' => $this->currentStage($group, $stages),
            'stages' => $stages->publicStages(),
            'jobs' => $this->jobPayload($group, $stages),
        ];

        return response()->json($response);
    }

    /**
     * Return generation status for the verified lead.
     */
    public function status(
        Request $request,
        string $groupUuid,
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages
    ): JsonResponse {
        $scope = 'generation.status';

        $tenant = $this->tenantFromRequest($request, $scope);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('jobs.asset')
            ->firstOrFail();

        $response = [
            'status' => $group->status,
            'current_stage' => $this->currentStage($group, $stages),
            'stages' => $stages->publicStages(),
            'jobs' => $this->jobPayload($group, $stages),
        ];

        return response()->json($response);
    }

    /**
     * Return generated result URLs for the verified lead.
     */
    public function results(
        Request $request,
        string $groupUuid,
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages
    ): JsonResponse {
        $scope = 'generation.results';

        $tenant = $this->tenantFromRequest($request, $scope);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('jobs.asset')
            ->firstOrFail();

        $results = [];

        foreach ($stages->sortByStageOrder($group->jobs) as $job) {
            if ($job->status !== 'completed' || ! $job->asset) {
                break;
            }

            $results[] = [
                'period' => $job->period,
                'label' => $stages->label($job->period),
                'caption' => $stages->caption($job->period),
                'url' => '/api/v1/assets/'.$job->asset->uuid,
            ];
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'disclaimer' => __('messages.result_disclaimer'),
        ]);
    }

    /**
     * Build public job data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function jobPayload(GenerationGroup $group, GenerationStageCatalog $stages): array
    {
        $previousStagesCompleted = true;
        $payload = [];

        foreach ($stages->sortByStageOrder($group->jobs) as $job) {
            $assetUrl = $previousStagesCompleted && $job->status === 'completed' && $job->asset
                ? '/api/v1/assets/'.$job->asset->uuid
                : null;

            $payload[] = [
                'id' => $job->uuid,
                'period' => $job->period,
                'key' => $job->period,
                'label' => $stages->label($job->period),
                'caption' => $stages->caption($job->period),
                'status' => $job->status,
                'error' => $job->status === 'failed' ? $job->last_error : null,
                'asset_url' => $assetUrl,
            ];

            if ($job->status !== 'completed') {
                $previousStagesCompleted = false;
            }
        }

        return $payload;
    }

    /**
     * Resolve the current visible stage.
     *
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

    /**
     * Get the tenant resolved by tenant middleware.
     */
    private function tenantFromRequest(Request $request, string $scope): Tenant
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}
