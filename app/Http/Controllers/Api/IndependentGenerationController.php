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
    public function store(
        StoreIndependentGenerationRequest $request,
        LeadSessionService $leadSessionService,
        IndependentGenerationService $generations
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

        return (new GenerationRequestResource($generation->load(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $generation, LeadSessionService $leadSessionService): GenerationRequestResource
    {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $model = GenerationRequest::query()
            ->where('uuid', $generation)
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->with(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])
            ->firstOrFail();

        return new GenerationRequestResource($model);
    }

    public function regenerate(
        Request $request,
        string $generation,
        LeadSessionService $leadSessionService,
        IndependentGenerationService $generations
    ): GenerationRequestResource
    {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));

        $model = GenerationRequest::query()
            ->where('uuid', $generation)
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->with(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])
            ->firstOrFail();

        $attempt = $model->attempts()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'ai_model_id' => $model->attempts->last()?->ai_model_id,
            'attempt_number' => $model->attempts->count() + 1,
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'prompt' => $model->output?->prompt ?? '',
        ]);

        $model->update(['status' => 'queued']);

        $generations->dispatchAttempt($attempt);

        return new GenerationRequestResource($model->refresh()->load(['sourceImage', 'category', 'output', 'currentAsset', 'attempts']));
    }

    private function tenantFromRequest(Request $request): Tenant
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}
