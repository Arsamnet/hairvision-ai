<?php

namespace App\Services\AI;

use App\Models\AiModelAccess;
use App\Models\GenerationJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Manages AI model access records.
 *
 * @since 1.0.0
 */
class AiModelManager
{
    /**
     * Create a new ai model manager instance.
     *
     * @param  AiModelResolver  $resolver  The resolver value.
     * @param  ExecutableAiModelSyncService  $executableModels  The executable models value.
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly AiModelResolver $resolver,
        private readonly ExecutableAiModelSyncService $executableModels,
    ) {}

    /**
     * Paginate AI model access records for admin screens.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return LengthAwarePaginator Paginated result set.
     *
     * @since 1.0.0
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $scope = 'ai.model_manager.paginate';

        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'model_key', 'usage_category_key', 'credit_used', 'usage_count', 'priority', 'last_used_at'],
            'created_at'
        );

        $paginator = $this->query($request, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();

        return $paginator;
    }

    /**
     * Build the filtered AI model access query.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  PaginationParameters  $pagination  The pagination value.
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function query(Request $request, PaginationParameters $pagination): Builder
    {
        $scope = 'ai.model_manager.query';

        $query = AiModelAccess::query()
            ->withCount([
                'clinics',
                'usageLogs',
                'usageLogs as completed_usage_logs_count' => fn (Builder $query) => $query->where('status', 'completed'),
            ]);

        SearchFilter::apply($query, $pagination->search, ['name', 'description', 'model_key', 'usage_category_key']);

        $query
            ->when($request->filled('model_key'), fn (Builder $query) => $query->where('model_key', $request->string('model_key')->toString()))
            ->when($request->filled('usage_category_key'), fn (Builder $query) => $query->where('usage_category_key', $request->string('usage_category_key')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('flag'), function (Builder $query) use ($request): void {
                match ($request->string('flag')->toString()) {
                    'demo' => $query->where('is_demo', true),
                    default => null,
                };
            })
            ->when($request->filled('credit_status'), function (Builder $query) use ($request): void {
                match ($request->string('credit_status')->toString()) {
                    'unlimited' => $query->whereNull('credit_limit'),
                    'finished' => $query->whereNotNull('credit_limit')->whereRaw('(credit_used + credit_reserved) >= credit_limit'),
                    'available' => $query->where(function (Builder $query): void {
                        $query->whereNull('credit_limit')->orWhereRaw('(credit_used + credit_reserved) < credit_limit');
                    }),
                    default => null,
                };
            });

        DateRangeFilter::apply($query, $request);

        return $query->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * Create an AI model access record.
     *
     * @param  array<string, mixed>  $data
     * @param  User  $actor  Authenticated user performing the operation.
     * @return AiModelAccess Resolved operation result.
     *
     * @since 1.0.0
     */
    public function create(array $data, User $actor): AiModelAccess
    {
        $scope = 'ai.model_manager.create';
        $step = 0;

        $step++;
        $step = $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key'], $scope, $step);

        $access = AiModelAccess::create($this->attributes($data) + [
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->executableModels->sync($access, $actor);

        return $access;
    }

    /**
     * Update an AI model access record.
     *
     * @param  array<string, mixed>  $data
     * @param  AiModelAccess  $access  The access value.
     * @param  User  $actor  Authenticated user performing the operation.
     * @return AiModelAccess Resolved operation result.
     *
     * @since 1.0.0
     */
    public function update(AiModelAccess $access, array $data, User $actor): AiModelAccess
    {
        $scope = 'ai.model_manager.update';
        $step = 0;

        $step++;
        $step = $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key'], $scope, $step);

        $access->update($this->attributes($data, $access) + [
            'updated_by' => $actor->id,
        ]);

        $access->refresh();
        $this->executableModels->sync($access, $actor);

        return $access;
    }

    /**
     * Toggle AI model access active state.
     *
     * @param  AiModelAccess  $access  The access value.
     * @param  User  $actor  Authenticated user performing the operation.
     * @return AiModelAccess Resolved operation result.
     *
     * @since 1.0.0
     */
    public function toggle(AiModelAccess $access, User $actor): AiModelAccess
    {
        $scope = 'ai.model_manager.toggle';
        $step = 0;

        $step++;
        $access->forceFill([
            'is_active' => ! $access->is_active,
            'updated_by' => $actor->id,
        ])->save();

        $access->refresh();
        $this->executableModels->sync($access, $actor);

        return $access;
    }

    /**
     * Delete an AI model access record.
     *
     * @param  AiModelAccess  $access  The access value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function delete(AiModelAccess $access): void
    {
        $scope = 'ai.model_manager.delete';
        $step = 0;

        $step++;
        $accessId = $access->id;

        AiModelAccess::query()
            ->whereKey($accessId)
            ->delete();

    }

    /**
     * Resolve the usable model access for a clinic and usage category.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  int|null  $aiModelAccessId  The ai model access id value.
     * @return AiModelAccess Resolved operation result.
     *
     * @since 1.0.0
     */
    public function resolveForClinic(Tenant $clinic, string $usageCategoryKey, ?int $aiModelAccessId = null): AiModelAccess
    {
        $scope = 'ai.model_manager.resolve_for_clinic';
        $step = 0;

        $step++;
        $query = AiModelAccess::query()
            ->active()
            ->where('usage_category_key', $usageCategoryKey)
            ->whereHas('clinics', fn (Builder $query) => $query
                ->where('tenants.id', $clinic->id)
                ->where('clinic_ai_model_access.is_active', true));

        if ($aiModelAccessId) {
            $query->whereKey($aiModelAccessId);

            $step++;
        }

        $accesses = $query
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (AiModelAccess $access): bool => $access->hasAvailableCredit())
            ->values();

        $step++;
        if ($accesses->isEmpty()) {
            $hasAssignedModels = $query->exists();

            if ($hasAssignedModels) {
                throw ValidationException::withMessages([
                    'ai_model_access' => __('ai_models.errors.credit_finished'),
                ]);
            }

            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.no_assigned_model'),
            ]);
        }

        $loads = $this->activeQueueLoads($clinic, $accesses->pluck('id')->all());

        $withCapacity = $accesses->filter(fn (AiModelAccess $access): bool => ($loads[$access->id] ?? 0) < $this->concurrencyLimitForAccess($access));

        $selected = $withCapacity->isNotEmpty()
            ? $this->sortAccessesByPriority($withCapacity, $loads)->first()
            : $this->sortAccessesByLoad($accesses, $loads)->first();

        return $selected;
    }

    /**
     * Return active access records assigned to a clinic and category.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    public function availableForClinic(Tenant $clinic, string $usageCategoryKey): Collection
    {
        $scope = 'ai.model_manager.available_for_clinic';
        $step = 0;

        $step++;
        $accesses = AiModelAccess::query()
            ->active()
            ->where('usage_category_key', $usageCategoryKey)
            ->whereHas('clinics', fn (Builder $query) => $query
                ->where('tenants.id', $clinic->id)
                ->where('clinic_ai_model_access.is_active', true))
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $accesses;
    }

    /**
     * Count unfinished legacy generation jobs per assigned model.
     *
     * @param  array<int, int>  $accessIds
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @return array<int, int>
     *
     * @since 1.0.0
     */
    private function activeQueueLoads(Tenant $clinic, array $accessIds): array
    {
        if ($accessIds === []) {
            return [];
        }

        return GenerationJob::query()
            ->where('tenant_id', $clinic->id)
            ->whereIn('ai_model_access_id', $accessIds)
            ->whereIn('status', ['pending', 'processing'])
            ->selectRaw('ai_model_access_id, count(*) as aggregate')
            ->groupBy('ai_model_access_id')
            ->pluck('aggregate', 'ai_model_access_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Execute the sort accesses by priority operation.
     *
     * @param  Collection<int, AiModelAccess>  $accesses
     * @param  array<int, int>  $loads
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    private function sortAccessesByPriority(Collection $accesses, array $loads): Collection
    {
        return $accesses
            ->sort(fn (AiModelAccess $first, AiModelAccess $second): int => [
                $first->priority ?? PHP_INT_MAX,
                $loads[$first->id] ?? 0,
                $first->id,
            ] <=> [
                $second->priority ?? PHP_INT_MAX,
                $loads[$second->id] ?? 0,
                $second->id,
            ])
            ->values();
    }

    /**
     * Execute the sort accesses by load operation.
     *
     * @param  Collection<int, AiModelAccess>  $accesses
     * @param  array<int, int>  $loads
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    private function sortAccessesByLoad(Collection $accesses, array $loads): Collection
    {
        return $accesses
            ->sort(fn (AiModelAccess $first, AiModelAccess $second): int => [
                $loads[$first->id] ?? 0,
                $first->priority ?? PHP_INT_MAX,
                $first->id,
            ] <=> [
                $loads[$second->id] ?? 0,
                $second->priority ?? PHP_INT_MAX,
                $second->id,
            ])
            ->values();
    }

    /**
     * Execute the concurrency limit for access operation.
     *
     * @param  AiModelAccess  $access  The access value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    private function concurrencyLimitForAccess(AiModelAccess $access): int
    {
        return max(1, (int) data_get(
            $access->settings,
            'concurrency_limit',
            data_get(config('ai-models.models.'.$access->model_key.'.defaults'), 'concurrency_limit', 1)
        ));
    }

    /**
     * Validate model and usage category config keys.
     *
     * @param  string  $modelKey  The model key value.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  string|null  $scope  The scope value.
     * @param  int  $step  The step value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function validateConfigKeys(
        string $modelKey,
        string $usageCategoryKey,
        ?string $scope = null,
        int $step = 0
    ): int {
        $scope ??= 'ai.model_manager.validate_config_keys';

        $step++;
        if (! $this->resolver->hasModel($modelKey)) {
            throw ValidationException::withMessages([
                'model_key' => __('ai_models.errors.unknown_model'),
            ]);
        }

        if (! $this->resolver->hasUsageCategory($usageCategoryKey)) {
            throw ValidationException::withMessages([
                'usage_category_key' => __('ai_models.errors.unknown_usage_category'),
            ]);
        }

        if (! $this->resolver->modelSupportsUsageCategory($modelKey, $usageCategoryKey)) {
            throw ValidationException::withMessages([
                'usage_category_key' => __('ai_models.errors.unsupported_usage_category'),
            ]);
        }

        return $step + 1;
    }

    /**
     * Build sanitized attributes for persistence.
     *
     * @param  array<string, mixed>  $data
     * @param  AiModelAccess|null  $access  The access value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function attributes(array $data, ?AiModelAccess $access = null): array
    {
        $credentials = $access?->credentials ?? [];

        foreach ((array) ($data['credentials'] ?? []) as $key => $value) {
            if (filled($value)) {
                $credentials[$key] = $value;
            }
        }

        return [
            'model_key' => $data['model_key'],
            'usage_category_key' => $data['usage_category_key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'credentials' => array_filter($credentials, fn (mixed $value): bool => filled($value)),
            'settings' => $this->filteredSettings(
                (array) ($data['settings'] ?? $access?->settings ?? []),
                (string) $data['model_key'],
            ),
            'prompt' => null,
            'credit_limit' => filled($data['credit_limit'] ?? null) ? (int) $data['credit_limit'] : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => filled($data['priority'] ?? null) ? (int) $data['priority'] : null,
            'is_demo' => (bool) ($data['is_demo'] ?? $access?->is_demo ?? false),
            'is_protected' => false,
        ];
    }

    /**
     * Remove empty settings recursively.
     *
     * @param  array<string, mixed>  $settings
     * @param  string|null  $modelKey  Model type for provider-specific cleanup.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function filteredSettings(array $settings, ?string $modelKey = null): array
    {
        unset($settings['stage_prompts']);

        if ($modelKey === 'openai') {
            unset($settings['response_format']);
        }

        $settings = collect($settings)
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->filteredSettings($value) : $value)
            ->filter(fn (mixed $value): bool => is_array($value) ? $value !== [] : filled($value))
            ->all();

        return InputImageProcessingSettings::normalize($settings);
    }

    /**
     * Write a safe ordered debug step for non-request workflows.
     *
     * @param  array<string, mixed>  $context
     */
}
