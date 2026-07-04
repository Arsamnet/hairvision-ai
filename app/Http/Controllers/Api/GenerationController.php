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
use App\Support\DebugStepLogger;
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

        DebugStepLogger::step($request, $scope, 'Request received', [
            'upload_id' => $request->post('upload_id'),
        ]);

        $tenant = $this->tenantFromRequest($request, $scope);

        DebugStepLogger::step($request, $scope, 'Tenant resolved', [
            'tenant_id' => $tenant->id,
        ]);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        DebugStepLogger::step($request, $scope, 'Verified lead resolved', [
            'lead_id' => $lead->id,
            'tenant_id' => $tenant->id,
        ]);

        $upload = Upload::query()
            ->where('uuid', '=', $request->post('upload_id'))
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        DebugStepLogger::step($request, $scope, 'Upload resolved', [
            'upload_id' => $upload->id,
            'upload_uuid' => $upload->uuid,
        ]);

        $group = $generationService->createForUpload($upload);
        $group->refresh()->load('jobs.asset');

        DebugStepLogger::step($request, $scope, 'Generation group created', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'status' => $group->status,
            'jobs_count' => $group->jobs->count(),
        ]);

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

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'generation_group_uuid' => $group->uuid,
            'current_stage' => $response['current_stage']['key'] ?? null,
            'jobs_count' => count($response['jobs']),
        ]);

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

        DebugStepLogger::step($request, $scope, 'Request received', [
            'generation_group_uuid' => $groupUuid,
        ]);

        $tenant = $this->tenantFromRequest($request, $scope);

        DebugStepLogger::step($request, $scope, 'Tenant resolved', [
            'tenant_id' => $tenant->id,
        ]);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        DebugStepLogger::step($request, $scope, 'Verified lead resolved', [
            'lead_id' => $lead->id,
            'tenant_id' => $tenant->id,
        ]);

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('jobs.asset')
            ->firstOrFail();

        DebugStepLogger::step($request, $scope, 'Generation group resolved', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'status' => $group->status,
            'jobs_count' => $group->jobs->count(),
        ]);

        $response = [
            'status' => $group->status,
            'current_stage' => $this->currentStage($group, $stages),
            'stages' => $stages->publicStages(),
            'jobs' => $this->jobPayload($group, $stages),
        ];

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'generation_group_uuid' => $group->uuid,
            'current_stage' => $response['current_stage']['key'] ?? null,
            'jobs_count' => count($response['jobs']),
        ]);

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

        DebugStepLogger::step($request, $scope, 'Request received', [
            'generation_group_uuid' => $groupUuid,
        ]);

        $tenant = $this->tenantFromRequest($request, $scope);

        DebugStepLogger::step($request, $scope, 'Tenant resolved', [
            'tenant_id' => $tenant->id,
        ]);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        DebugStepLogger::step($request, $scope, 'Verified lead resolved', [
            'lead_id' => $lead->id,
            'tenant_id' => $tenant->id,
        ]);

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('assets')
            ->firstOrFail();

        DebugStepLogger::step($request, $scope, 'Generation group resolved', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'assets_count' => $group->assets->count(),
        ]);

        $results = collect($stages->sortByStageOrder($group->assets))
            ->map(fn ($asset): array => [
                'period' => $asset->period,
                'label' => $stages->label($asset->period),
                'caption' => $stages->caption($asset->period),
                'url' => '/api/v1/assets/'.$asset->uuid,
            ])
            ->values()
            ->all();

        DebugStepLogger::step($request, $scope, 'Results payload prepared', [
            'generation_group_uuid' => $group->uuid,
            'results_count' => count($results),
        ]);

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
            DebugStepLogger::step($request, $scope, 'Tenant missing from request attributes');

            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}