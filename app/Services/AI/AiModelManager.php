<?php

namespace App\Services\AI;

use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DebugStepLogger;
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
    public function __construct(private readonly AiModelResolver $resolver) {}

    /**
     * Paginate AI model access records for admin screens.
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $scope = 'ai.model_manager.paginate';

        DebugStepLogger::step($request, $scope, 'Request received', [
            'user_id' => $request->user()?->id,
            'has_search' => filled($request->input('search')),
            'model_key' => $request->input('model_key'),
            'usage_category_key' => $request->input('usage_category_key'),
            'status' => $request->input('status'),
            'flag' => $request->input('flag'),
            'credit_status' => $request->input('credit_status'),
        ]);

        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'model_key', 'usage_category_key', 'credit_used', 'priority', 'last_used_at'],
            'created_at'
        );

        DebugStepLogger::step($request, $scope, 'Pagination parameters resolved', [
            'page' => $pagination->page,
            'per_page' => $pagination->perPage,
            'sort' => $pagination->sort,
            'direction' => $pagination->direction,
            'has_search' => filled($pagination->search),
        ]);

        $paginator = $this->query($request, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();

        DebugStepLogger::step($request, $scope, 'Paginator prepared', [
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);

        return $paginator;
    }

    /**
     * Build the filtered AI model access query.
     */
    public function query(Request $request, PaginationParameters $pagination): Builder
    {
        $scope = 'ai.model_manager.query';

        DebugStepLogger::step($request, $scope, 'Query building started', [
            'has_search' => filled($pagination->search),
            'search_length' => is_string($pagination->search) ? mb_strlen($pagination->search) : 0,
            'sort' => $pagination->sort,
            'direction' => $pagination->direction,
        ]);

        $query = AiModelAccess::query()->withCount('clinics');

        SearchFilter::apply($query, $pagination->search, ['name', 'description', 'model_key', 'usage_category_key']);

        DebugStepLogger::step($request, $scope, 'Search filter applied', [
            'has_search' => filled($pagination->search),
        ]);

        $query
            ->when($request->filled('model_key'), fn (Builder $query) => $query->where('model_key', $request->string('model_key')->toString()))
            ->when($request->filled('usage_category_key'), fn (Builder $query) => $query->where('usage_category_key', $request->string('usage_category_key')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('flag'), function (Builder $query) use ($request): void {
                match ($request->string('flag')->toString()) {
                    'demo' => $query->where('is_demo', true),
                    'protected' => $query->where('is_protected', true),
                    default => null,
                };
            })
            ->when($request->filled('credit_status'), function (Builder $query) use ($request): void {
                match ($request->string('credit_status')->toString()) {
                    'unlimited' => $query->whereNull('credit_limit'),
                    'finished' => $query->whereNotNull('credit_limit')->whereColumn('credit_used', '>=', 'credit_limit'),
                    'available' => $query->where(function (Builder $query): void {
                        $query->whereNull('credit_limit')->orWhereColumn('credit_used', '<', 'credit_limit');
                    }),
                    default => null,
                };
            });

        DebugStepLogger::step($request, $scope, 'Field filters applied', [
            'has_model_key_filter' => $request->filled('model_key'),
            'has_usage_category_filter' => $request->filled('usage_category_key'),
            'has_status_filter' => $request->filled('status'),
            'has_flag_filter' => $request->filled('flag'),
            'has_credit_status_filter' => $request->filled('credit_status'),
        ]);

        DateRangeFilter::apply($query, $request);

        DebugStepLogger::step($request, $scope, 'Date range filter applied', [
            'has_date_from' => $request->filled('date_from'),
            'has_date_to' => $request->filled('date_to'),
        ]);

        DebugStepLogger::step($request, $scope, 'Query ordering applied', [
            'sort' => $pagination->sort,
            'direction' => $pagination->direction,
        ]);

        return $query->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * Create an AI model access record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): AiModelAccess
    {
        $scope = 'ai.model_manager.create';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'actor_id' => $actor->id,
            'model_key' => $data['model_key'] ?? null,
            'usage_category_key' => $data['usage_category_key'] ?? null,
            'has_credentials' => ! empty($data['credentials'] ?? []),
            'settings_count' => count((array) ($data['settings'] ?? [])),
            'has_prompt' => filled($data['prompt'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_demo' => (bool) ($data['is_demo'] ?? false),
            'is_protected' => (bool) ($data['is_protected'] ?? false),
        ]);

        $step = $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key'], $scope, $step);

        $access = AiModelAccess::create($this->attributes($data) + [
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->workflowStep($scope, $step, 'AI model access created', [
            'actor_id' => $actor->id,
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'usage_category_key' => $access->usage_category_key,
            'is_active' => $access->is_active,
            'is_demo' => $access->is_demo,
            'is_protected' => $access->is_protected,
        ]);

        return $access;
    }

    /**
     * Update an AI model access record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(AiModelAccess $access, array $data, User $actor): AiModelAccess
    {
        $scope = 'ai.model_manager.update';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'actor_id' => $actor->id,
            'ai_model_access_id' => $access->id,
            'current_model_key' => $access->model_key,
            'new_model_key' => $data['model_key'] ?? null,
            'new_usage_category_key' => $data['usage_category_key'] ?? null,
            'has_new_credentials' => ! empty($data['credentials'] ?? []),
            'settings_count' => count((array) ($data['settings'] ?? [])),
            'has_prompt' => filled($data['prompt'] ?? null),
        ]);

        $step = $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key'], $scope, $step);

        $access->update($this->attributes($data, $access) + [
            'updated_by' => $actor->id,
        ]);

        $access->refresh();

        $this->workflowStep($scope, $step, 'AI model access updated', [
            'actor_id' => $actor->id,
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'usage_category_key' => $access->usage_category_key,
            'is_active' => $access->is_active,
            'is_demo' => $access->is_demo,
            'is_protected' => $access->is_protected,
        ]);

        return $access;
    }

    /**
     * Toggle AI model access active state.
     */
    public function toggle(AiModelAccess $access, User $actor): AiModelAccess
    {
        $scope = 'ai.model_manager.toggle';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'actor_id' => $actor->id,
            'ai_model_access_id' => $access->id,
            'current_is_active' => $access->is_active,
        ]);

        $access->forceFill([
            'is_active' => ! $access->is_active,
            'updated_by' => $actor->id,
        ])->save();

        $access->refresh();

        $this->workflowStep($scope, $step, 'AI model access toggled', [
            'actor_id' => $actor->id,
            'ai_model_access_id' => $access->id,
            'new_is_active' => $access->is_active,
        ]);

        return $access;
    }

    /**
     * Delete an AI model access record.
     */
    public function delete(AiModelAccess $access): void
    {
        $scope = 'ai.model_manager.delete';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'is_protected' => $access->is_protected,
        ]);

        if ($access->is_protected) {
            $this->workflowStep($scope, $step, 'Delete blocked for protected access', [
                'ai_model_access_id' => $access->id,
            ]);

            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.protected_delete'),
            ]);
        }

        $accessId = $access->id;

        AiModelAccess::query()
            ->whereKey($accessId)
            ->delete();

        $this->workflowStep($scope, $step, 'AI model access deleted', [
            'ai_model_access_id' => $accessId,
        ]);
    }
    
    /**
     * Resolve the usable model access for a clinic and usage category.
     */
    public function resolveForClinic(Tenant $clinic, string $usageCategoryKey, ?int $aiModelAccessId = null): AiModelAccess
    {
        $scope = 'ai.model_manager.resolve_for_clinic';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'clinic_id' => $clinic->id,
            'usage_category_key' => $usageCategoryKey,
            'requested_ai_model_access_id' => $aiModelAccessId,
        ]);

        $query = AiModelAccess::query()
            ->active()
            ->where('usage_category_key', $usageCategoryKey)
            ->whereHas('clinics', fn (Builder $query) => $query
                ->where('tenants.id', $clinic->id)
                ->where('clinic_ai_model_access.is_active', true));

        if ($aiModelAccessId) {
            $query->whereKey($aiModelAccessId);

            $step = $this->workflowStep($scope, $step, 'Specific access filter applied', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $aiModelAccessId,
            ]);
        }

        $access = $query
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        $step = $this->workflowStep($scope, $step, 'Access lookup completed', [
            'clinic_id' => $clinic->id,
            'usage_category_key' => $usageCategoryKey,
            'access_found' => $access !== null,
            'ai_model_access_id' => $access?->id,
            'model_key' => $access?->model_key,
            'priority' => $access?->priority,
        ]);

        if (! $access) {
            $this->workflowStep($scope, $step, 'No assigned model found', [
                'clinic_id' => $clinic->id,
                'usage_category_key' => $usageCategoryKey,
                'requested_ai_model_access_id' => $aiModelAccessId,
            ]);

            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.no_assigned_model'),
            ]);
        }

        if (! $access->hasAvailableCredit()) {
            $this->workflowStep($scope, $step, 'Resolved access has no available credit', [
                'clinic_id' => $clinic->id,
                'ai_model_access_id' => $access->id,
                'model_key' => $access->model_key,
                'has_unlimited_credit' => $access->hasUnlimitedCredit(),
            ]);

            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.credit_finished'),
            ]);
        }

        $this->workflowStep($scope, $step, 'Usable access resolved', [
            'clinic_id' => $clinic->id,
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'usage_category_key' => $access->usage_category_key,
            'has_unlimited_credit' => $access->hasUnlimitedCredit(),
        ]);

        return $access;
    }

    /**
     * Return active access records assigned to a clinic and category.
     *
     * @return Collection<int, AiModelAccess>
     */
    public function availableForClinic(Tenant $clinic, string $usageCategoryKey): Collection
    {
        $scope = 'ai.model_manager.available_for_clinic';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'clinic_id' => $clinic->id,
            'usage_category_key' => $usageCategoryKey,
        ]);

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

        $this->workflowStep($scope, $step, 'Available accesses resolved', [
            'clinic_id' => $clinic->id,
            'usage_category_key' => $usageCategoryKey,
            'count' => $accesses->count(),
            'access_ids' => $accesses->pluck('id')->values()->all(),
        ]);

        return $accesses;
    }

    /**
     * Validate model and usage category config keys.
     */
    public function validateConfigKeys(
        string $modelKey,
        string $usageCategoryKey,
        ?string $scope = null,
        int $step = 0
    ): int {
        $scope ??= 'ai.model_manager.validate_config_keys';

        $step = $this->workflowStep($scope, $step, 'Validating config keys', [
            'model_key' => $modelKey,
            'usage_category_key' => $usageCategoryKey,
        ]);

        if (! $this->resolver->hasModel($modelKey)) {
            $this->workflowStep($scope, $step, 'Unknown model key', [
                'model_key' => $modelKey,
                'usage_category_key' => $usageCategoryKey,
            ]);

            throw ValidationException::withMessages([
                'model_key' => __('ai_models.errors.unknown_model'),
            ]);
        }

        if (! $this->resolver->hasUsageCategory($usageCategoryKey)) {
            $this->workflowStep($scope, $step, 'Unknown usage category key', [
                'model_key' => $modelKey,
                'usage_category_key' => $usageCategoryKey,
            ]);

            throw ValidationException::withMessages([
                'usage_category_key' => __('ai_models.errors.unknown_usage_category'),
            ]);
        }

        if (! $this->resolver->modelSupportsUsageCategory($modelKey, $usageCategoryKey)) {
            $this->workflowStep($scope, $step, 'Model does not support usage category', [
                'model_key' => $modelKey,
                'usage_category_key' => $usageCategoryKey,
            ]);

            throw ValidationException::withMessages([
                'usage_category_key' => __('ai_models.errors.unsupported_usage_category'),
            ]);
        }

        return $this->workflowStep($scope, $step, 'Config keys validated', [
            'model_key' => $modelKey,
            'usage_category_key' => $usageCategoryKey,
        ]);
    }

    /**
     * Build sanitized attributes for persistence.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
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
            'settings' => $this->filteredSettings((array) ($data['settings'] ?? $access?->settings ?? [])),
            'prompt' => $data['prompt'] ?? null,
            'credit_limit' => filled($data['credit_limit'] ?? null) ? (int) $data['credit_limit'] : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => filled($data['priority'] ?? null) ? (int) $data['priority'] : null,
            'is_demo' => (bool) ($data['is_demo'] ?? $access?->is_demo ?? false),
            'is_protected' => (bool) ($data['is_protected'] ?? $access?->is_protected ?? false),
        ];
    }

    /**
     * Remove empty settings recursively.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function filteredSettings(array $settings): array
    {
        return collect($settings)
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->filteredSettings($value) : $value)
            ->filter(fn (mixed $value): bool => is_array($value) ? $value !== [] : filled($value))
            ->all();
    }

    /**
     * Write a safe ordered debug step for non-request workflows.
     *
     * @param  array<string, mixed>  $context
     */
    private function workflowStep(string $scope, int $step, string $message, array $context = []): int
    {
        $step++;

        DebugStepLogger::workflowStep($scope, $step, $message, $context);

        return $step;
    }
}