<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGenerationRequest;
use App\Http\Resources\GenerationRequestResource;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\LeadSourceImage;
use App\Models\Tenant;
use App\Models\Upload;
use App\Services\Generation\GenerationStageCatalog;
use App\Services\Generation\GenerationResumeTokenService;
use App\Services\Generation\IndependentGenerationService;
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
     *
     * @param  StoreGenerationRequest  $request  Incoming HTTP request.
     * @param  GenerationService  $generationService  The generation service value.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     * @param  IndependentGenerationService  $independentGenerations  The independent generations value.
     * @param  GenerationResumeTokenService  $resumeTokens  Session-bound generation credential manager.
     * @return JsonResponse JSON response payload.
     *
     * @since 1.0.0
     */
    public function store(
        StoreGenerationRequest $request,
        GenerationService $generationService,
        LeadSessionService $leadSessionService,
        GenerationStageCatalog $stages,
        IndependentGenerationService $independentGenerations,
        GenerationResumeTokenService $resumeTokens,
    ): JsonResponse {
        $scope = 'generation.store';

        $tenant = $this->tenantFromRequest($request, $scope);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        if ($request->filled('source_image_id')) {
            $source = LeadSourceImage::query()
                ->where('uuid', $request->string('source_image_id')->toString())
                ->where('tenant_id', $tenant->id)
                ->where('lead_id', $lead->id)
                ->firstOrFail();

            $category = AiModelCategory::query()
                ->where('uuid', $request->string('category_id')->toString())
                ->firstOrFail();

            $output = AiModelCategoryOutput::query()
                ->where('uuid', $request->string('output_id')->toString())
                ->where('ai_model_category_id', $category->id)
                ->firstOrFail();

            $idempotencyKey = $request->string('idempotency_key')->toString()
                ?: (string) $request->header('Idempotency-Key');

            $generation = $independentGenerations->create(
                $lead,
                $source,
                $category,
                $output,
                $idempotencyKey
            );
            $resumeTokens->remember($request, $generation);

            return (new GenerationRequestResource($generation->load(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])))
                ->response()
                ->setStatusCode(Response::HTTP_ACCEPTED);
        }

        $upload = Upload::query()
            ->where('uuid', '=', $request->post('upload_id'))
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $usageCategoryKey = (string) $request->input('usage_category_key', 'hair_transplant');

        $group = $generationService->createForUpload($upload, $usageCategoryKey);
        $group->refresh()->load('jobs.asset');

        $response = [
            'success' => true,
            'generation_group' => [
                'id' => $group->uuid,
                'status' => $group->status,
            ],
            'current_stage' => $this->currentStage($group, $stages),
            'stages' => $this->stagePayload($group, $stages, $usageCategoryKey),
            'jobs' => $this->jobPayload($group, $stages),
        ];

        return response()->json($response, Response::HTTP_ACCEPTED);
    }

    /**
     * Return generation status for the verified lead.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $groupUuid  The group uuid value.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     * @return JsonResponse JSON response payload.
     *
     * @since 1.0.0
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
            'stages' => $this->stagePayload($group, $stages),
            'jobs' => $this->jobPayload($group, $stages),
        ];

        return response()->json($response);
    }

    /**
     * Return generated result URLs for the verified lead.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $groupUuid  The group uuid value.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     * @return JsonResponse JSON response payload.
     *
     * @since 1.0.0
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
            if ($job->status === 'completed' && $job->asset) {
                $results[] = [
                    'period' => $job->period,
                    'label' => $stages->label($job->period),
                    'caption' => $stages->caption($job->period),
                    'url' => '/api/v1/assets/'.$job->asset->uuid,
                ];
            }
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
     * @param  GenerationGroup  $group  The group value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    private function jobPayload(GenerationGroup $group, GenerationStageCatalog $stages): array
    {
        $payload = [];

        foreach ($stages->sortByStageOrder($group->jobs) as $job) {
            $assetUrl = $job->status === 'completed' && $job->asset
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
        }

        return $payload;
    }

    /**
     * Build stage metadata for the selected generation service.
     *
     * @param  GenerationGroup  $group  The group value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     * @param  string|null  $usageCategoryKey  The usage category key value.
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    private function stagePayload(
        GenerationGroup $group,
        GenerationStageCatalog $stages,
        ?string $usageCategoryKey = null
    ): array {
        if ($usageCategoryKey) {
            return $stages->publicStages($usageCategoryKey);
        }

        return $stages->publicStagesForKeys(
            $group->jobs
                ->pluck('period')
                ->map(fn (mixed $period): string => (string) $period)
                ->all()
        );
    }

    /**
     * Resolve the current visible stage.
     *
     * @param  GenerationGroup  $group  The group value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     * @return array<string, mixed>|null
     *
     * @since 1.0.0
     */
    private function currentStage(GenerationGroup $group, GenerationStageCatalog $stages): ?array
    {
        $job = collect($stages->sortByStageOrder($group->jobs))
            ->first(fn (GenerationJob $job): bool => in_array($job->status, ['processing', 'pending', 'queued', 'retrying', 'failed'], true));

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
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $scope  The scope value.
     * @return Tenant Resolved operation result.
     *
     * @since 1.0.0
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
