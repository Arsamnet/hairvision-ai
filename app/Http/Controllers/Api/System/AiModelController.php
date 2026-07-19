<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\System\StoreAiModelRequest;
use App\Http\Resources\AiModelResource;
use App\Models\AiModel;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Services\AI\AiModelTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * System API for executable AI model instances.
 *
 * @since 2.0.0
 */
class AiModelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $models = AiModel::query()
            ->with(['category', 'outputConfigs.output'])
            ->when($request->filled('category_id'), function (Builder $query) use ($request): void {
                $query->whereHas('category', fn (Builder $query) => $query->where('uuid', $request->string('category_id')->toString()));
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->paginate(min((int) $request->input('per_page', 15), (int) config('hairvision.api.max_per_page', 50)));

        return response()->json(AiModelResource::collection($models)->response()->getData(true));
    }

    public function store(StoreAiModelRequest $request, AiModelTypeRegistry $registry): JsonResponse
    {
        $data = $request->validated();
        $category = AiModelCategory::query()->where('uuid', $data['category_id'])->firstOrFail();
        $settings = $registry->validateSettings($data['type'], $data['settings'] ?? []);
        $credentials = $registry->validateCredentials($data['type'], $data['credentials'] ?? []);

        $model = DB::transaction(function () use ($request, $data, $category, $settings, $credentials, $registry): AiModel {
            $model = AiModel::query()->create([
                'ai_model_category_id' => $category->id,
                'type' => $data['type'],
                'name' => $data['name'],
                'status' => $data['status'] ?? 'draft',
                'priority' => $data['priority'] ?? 100,
                'concurrency_limit' => $data['concurrency_limit'] ?? (int) data_get(config('ai-models.types.'.$data['type']), 'defaults.concurrency_limit', 1),
                'settings' => $settings,
                'credentials' => $credentials,
                'health_status' => 'unknown',
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            foreach ($data['output_configs'] as $outputConfig) {
                $output = AiModelCategoryOutput::query()
                    ->where('uuid', $outputConfig['output_id'])
                    ->where('ai_model_category_id', $category->id)
                    ->firstOrFail();

                $model->outputConfigs()->create([
                    'ai_model_category_output_id' => $output->id,
                    'is_active' => (bool) ($outputConfig['is_active'] ?? true),
                    'configuration' => $registry->validateOutputConfig($model->type, $outputConfig['configuration'] ?? []),
                ]);
            }

            return $model;
        });

        return (new AiModelResource($model->load(['category', 'outputConfigs.output'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $model): AiModelResource
    {
        return new AiModelResource($this->findModel($model)->load(['category', 'outputConfigs.output']));
    }

    public function activate(string $model): AiModelResource
    {
        $aiModel = $this->findModel($model);

        if (! $aiModel->outputConfigs()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'output_configs' => __('messages.generation_output_invalid'),
            ]);
        }

        $aiModel->update([
            'status' => 'active',
            'paused_at' => null,
            'archived_at' => null,
        ]);

        return new AiModelResource($aiModel->refresh()->load(['category', 'outputConfigs.output']));
    }

    public function pause(string $model): AiModelResource
    {
        $aiModel = $this->findModel($model);
        $aiModel->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return new AiModelResource($aiModel->refresh()->load(['category', 'outputConfigs.output']));
    }

    public function destroy(string $model): JsonResponse
    {
        $aiModel = $this->findModel($model);

        if ($aiModel->attempts()->exists()) {
            $aiModel->update([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

            return response()->json([
                'message' => __('messages.save'),
                'data' => new AiModelResource($aiModel->refresh()),
            ], Response::HTTP_CONFLICT);
        }

        $aiModel->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    private function findModel(string $uuid): AiModel
    {
        return AiModel::query()->where('uuid', $uuid)->firstOrFail();
    }
}
