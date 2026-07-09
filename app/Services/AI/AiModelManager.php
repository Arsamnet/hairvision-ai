<?php

namespace App\Services\AI;

use App\Models\AiModelAccess;
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
    public function __construct(private readonly AiModelResolver $resolver) {}

    /**
     * Paginate AI model access records for admin screens.
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

        DateRangeFilter::apply($query, $request);

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

        $step++;
        $step = $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key'], $scope, $step);

        $access = AiModelAccess::create($this->attributes($data) + [
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
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

        $step++;
        $step = $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key'], $scope, $step);

        $access->update($this->attributes($data, $access) + [
            'updated_by' => $actor->id,
        ]);

        $access->refresh();

        return $access;
    }

    /**
     * Toggle AI model access active state.
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

        return $access;
    }

    /**
     * Delete an AI model access record.
     */
    public function delete(AiModelAccess $access): void
    {
        $scope = 'ai.model_manager.delete';
        $step = 0;

        $step++;
        if ($access->is_protected) {
            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.protected_delete'),
            ]);
        }

        $accessId = $access->id;

        AiModelAccess::query()
            ->whereKey($accessId)
            ->delete();

    }

    /**
     * Resolve the usable model access for a clinic and usage category.
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

        $access = $query
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        $step++;
        if (! $access) {
            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.no_assigned_model'),
            ]);
        }

        if (! $access->hasAvailableCredit()) {
            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.credit_finished'),
            ]);
        }

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
     * Validate model and usage category config keys.
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
}
