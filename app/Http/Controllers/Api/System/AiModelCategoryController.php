<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\System\StoreAiModelCategoryRequest;
use App\Http\Requests\Api\System\UpdateAiModelCategoryRequest;
use App\Http\Resources\AiModelCategoryResource;
use App\Models\AiModelCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * System CRUD API for AI model categories.
 *
 * @since 2.0.0
 */
class AiModelCategoryController extends Controller
{
    /**
     * Display the ai model category listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function index(Request $request): JsonResponse
    {
        $categories = AiModelCategory::query()
            ->withCount(['outputs', 'models'])
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn (Builder $query) => $query
                    ->where('code', 'like', $search)
                    ->orWhere('name', 'like', $search));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy($request->input('sort', 'sort_order'), $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc')
            ->paginate(min((int) $request->input('per_page', 15), (int) config('hairvision.api.max_per_page', 50)));

        return response()->json(AiModelCategoryResource::collection($categories)->response()->getData(true));
    }

    /**
     * Persist a new ai model category record.
     *
     * @param  StoreAiModelCategoryRequest  $request  Incoming HTTP request.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function store(StoreAiModelCategoryRequest $request): JsonResponse
    {
        $category = AiModelCategory::query()->create($request->validated() + [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return (new AiModelCategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the requested ai model category resource.
     *
     * @param  string  $category  The category value.
     * @return AiModelCategoryResource Resolved operation result.
     *
     * @since 2.0.0
     */
    public function show(string $category): AiModelCategoryResource
    {
        return new AiModelCategoryResource($this->findCategory($category)->load('outputs'));
    }

    /**
     * Update the requested ai model category record.
     *
     * @param  UpdateAiModelCategoryRequest  $request  Incoming HTTP request.
     * @param  string  $category  The category value.
     * @return AiModelCategoryResource Resolved operation result.
     *
     * @since 2.0.0
     */
    public function update(UpdateAiModelCategoryRequest $request, string $category): AiModelCategoryResource
    {
        $model = $this->findCategory($category);
        $model->update($request->validated() + ['updated_by' => $request->user()?->id]);

        return new AiModelCategoryResource($model->refresh()->load('outputs'));
    }

    /**
     * Delete the requested ai model category record.
     *
     * @param  string  $category  The category value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function destroy(string $category): JsonResponse
    {
        $model = $this->findCategory($category);

        if ($model->outputs()->exists() || $model->models()->exists()) {
            return response()->json([
                'message' => __('validation.exists', ['attribute' => 'category']),
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
}
