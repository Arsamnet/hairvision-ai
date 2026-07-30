<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreIndependentGenerationRequest;
use App\Http\Resources\GenerationRequestResource;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationRequest;
use App\Models\LeadSourceImage;
use App\Models\Tenant;
use App\Services\Generation\IndependentGenerationService;
use App\Services\Generation\GenerationResumeTokenService;
use App\Services\LeadSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public APIs for independent v2 output generations.
 *
 * @since 2.0.0
 */
class IndependentGenerationController extends Controller
{
    /**
     * Persist a new independent generation record.
     *
     * @param  StoreIndependentGenerationRequest  $request  Incoming HTTP request.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  IndependentGenerationService  $generations  The generations value.
     * @param  GenerationResumeTokenService  $resumeTokens  Session-bound generation credential manager.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function store(
        StoreIndependentGenerationRequest $request,
        LeadSessionService $leadSessionService,
        IndependentGenerationService $generations,
        GenerationResumeTokenService $resumeTokens,
    ): JsonResponse {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));

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

        $generation = $generations->create(
            $lead,
            $source,
            $category,
            $output,
            $request->string('idempotency_key')->toString()
        );
        $resumeTokens->remember($request, $generation);

        return (new GenerationRequestResource($generation->load(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Display the requested independent generation resource.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $generation  The generation value.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  GenerationResumeTokenService  $resumeTokens  Session-bound generation credential manager.
     * @return GenerationRequestResource Resolved operation result.
     *
     * @since 2.0.0
     */
    public function show(
        Request $request,
        string $generation,
        LeadSessionService $leadSessionService,
        GenerationResumeTokenService $resumeTokens,
    ): GenerationRequestResource
    {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $model = GenerationRequest::query()
            ->where('uuid', $generation)
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->with(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])
            ->firstOrFail();
        $resumeTokens->authorize($request, $model);
        $resumeTokens->remember($request, $model);

        return new GenerationRequestResource($model);
    }

    /**
     * Execute the regenerate operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $generation  The generation value.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  IndependentGenerationService  $generations  The generations value.
     * @param  GenerationResumeTokenService  $resumeTokens  Session-bound generation credential manager.
     * @return GenerationRequestResource Resolved operation result.
     *
     * @since 2.0.0
     */
    public function regenerate(
        Request $request,
        string $generation,
        LeadSessionService $leadSessionService,
        IndependentGenerationService $generations,
        GenerationResumeTokenService $resumeTokens,
    ): GenerationRequestResource {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));

        $model = GenerationRequest::query()
            ->where('uuid', $generation)
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->with(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])
            ->firstOrFail();
        $resumeTokens->authorize($request, $model);
        $regenerated = $generations->regenerate($model, $lead);
        $resumeTokens->remember($request, $regenerated);

        return new GenerationRequestResource($regenerated);
    }

    /**
     * Return generations retained by the current verified browser session.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  LeadSessionService  $leadSessionService  Verified lead resolver.
     * @param  GenerationResumeTokenService  $resumeTokens  Resume credential manager.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function resumable(
        Request $request,
        LeadSessionService $leadSessionService,
        GenerationResumeTokenService $resumeTokens,
    ): JsonResponse {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));
        $sessionTokens = $resumeTokens->sessionTokens($request);

        $generations = GenerationRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->whereIn('uuid', array_keys($sessionTokens))
            ->where('resumable_until', '>', now())
            ->with(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])
            ->latest('id')
            ->get()
            ->filter(fn (GenerationRequest $generation): bool => $resumeTokens->tokenFor($request, $generation) !== null)
            ->values();

        return response()->json([
            'data' => GenerationRequestResource::collection($generations),
        ]);
    }

    /**
     * Return an independently generated result as soon as it is available.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $generation  Public generation UUID.
     * @param  LeadSessionService  $leadSessionService  Verified lead resolver.
     * @param  GenerationResumeTokenService  $resumeTokens  Resume credential manager.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function results(
        Request $request,
        string $generation,
        LeadSessionService $leadSessionService,
        GenerationResumeTokenService $resumeTokens,
    ): JsonResponse {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));
        $model = GenerationRequest::query()
            ->where('uuid', $generation)
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->with(['output', 'currentAsset'])
            ->firstOrFail();
        $resumeTokens->authorize($request, $model);

        return response()->json([
            'data' => $model->currentAsset ? [[
                'output' => $model->output?->code,
                'status' => $model->status,
                'url' => '/api/v1/assets/'.$model->currentAsset->uuid.'/stream',
            ]] : [],
        ]);
    }

    /**
     * Execute the tenant from request operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return Tenant Resolved operation result.
     *
     * @since 2.0.0
     */
    private function tenantFromRequest(Request $request): Tenant
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}
