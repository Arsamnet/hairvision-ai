<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\System\StoreAiModelCategoryOutputRequest;
use App\Http\Requests\Api\System\UpdateAiModelCategoryOutputRequest;
use App\Http\Resources\AiModelCategoryOutputResource;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationRequest;
use App\Services\AI\ExecutableAiModelSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * System CRUD API for category outputs.
 *
 * @since 2.0.0
 */
class AiModelCategoryOutputController extends Controller
{
    /**
     * Display the ai model category output listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $category  The category value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function index(Request $request, string $category): JsonResponse
    {
        $categoryModel = $this->findCategory($category);

        $outputs = $categoryModel->outputs()
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('sort_order')
            ->paginate(min((int) $request->input('per_page', 20), (int) config('hairvision.api.max_per_page', 50)));

        return response()->json(AiModelCategoryOutputResource::collection($outputs)->response()->getData(true));
    }

    /**
     * Persist a new ai model category output record.
     *
     * @param  StoreAiModelCategoryOutputRequest  $request  Incoming HTTP request.
     * @param  string  $category  The category value.
     * @param  ExecutableAiModelSyncService  $executableModels  The executable models value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function store(
        StoreAiModelCategoryOutputRequest $request,
        string $category,
        ExecutableAiModelSyncService $executableModels
    ): JsonResponse {
        $categoryModel = $this->findCategory($category);
        $output = $categoryModel->outputs()->create($request->validated());
        $executableModels->syncForCategory($categoryModel);

        return (new AiModelCategoryOutputResource($output->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the requested ai model category output resource.
     *
     * @param  string  $category  The category value.
     * @param  string  $output  The output value.
     * @return AiModelCategoryOutputResource Resolved operation result.
     *
     * @since 2.0.0
     */
    public function show(string $category, string $output): AiModelCategoryOutputResource
    {
        return new AiModelCategoryOutputResource($this->findOutput($category, $output)->load('category'));
    }

    /**
     * Update the requested ai model category output record.
     *
     * @param  UpdateAiModelCategoryOutputRequest  $request  Incoming HTTP request.
     * @param  string  $category  The category value.
     * @param  string  $output  The output value.
     * @param  ExecutableAiModelSyncService  $executableModels  The executable models value.
     * @return AiModelCategoryOutputResource Resolved operation result.
     *
     * @since 2.0.0
     */
    public function update(
        UpdateAiModelCategoryOutputRequest $request,
        string $category,
        string $output,
        ExecutableAiModelSyncService $executableModels
    ): AiModelCategoryOutputResource {
        $model = $this->findOutput($category, $output);
        $model->update($request->validated());
        $executableModels->syncForCategory($model->category);

        return new AiModelCategoryOutputResource($model->refresh()->load('category'));
    }

    /**
     * Delete the requested ai model category output record.
     *
     * @param  string  $category  The category value.
     * @param  string  $output  The output value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function destroy(string $category, string $output): JsonResponse
    {
        $model = $this->findOutput($category, $output);

        $hasHistory = GenerationRequest::query()
            ->where('ai_model_category_output_id', $model->id)
            ->exists();

        if ($hasHistory || $model->modelOutputConfigs()->exists()) {
            return response()->json([
                'message' => __('validation.exists', ['attribute' => 'output']),
            ], Response::HTTP_CONFLICT);
        }

        $model->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Find category.
     *
     * @param  string  $uuid  The uuid value.
     * @return AiModelCategory Resolved operation result.
     *
     * @since 2.0.0
     */
    private function findCategory(string $uuid): AiModelCategory
    {
        return AiModelCategory::query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Find output.
     *
     * @param  string  $categoryUuid  The category uuid value.
     * @param  string  $outputUuid  The output uuid value.
     * @return AiModelCategoryOutput Resolved operation result.
     *
     * @since 2.0.0
     */
    private function findOutput(string $categoryUuid, string $outputUuid): AiModelCategoryOutput
    {
        $category = $this->findCategory($categoryUuid);

        return $category->outputs()->where('uuid', $outputUuid)->firstOrFail();
    }
}
