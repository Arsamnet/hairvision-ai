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
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates AI model access records, queries, and clinic assignment lookups.
 *
 * @since 1.0.0
 */
class AiModelManager
{
    public function __construct(private readonly AiModelResolver $resolver) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'model_key', 'usage_category_key', 'credit_used', 'priority', 'last_used_at'],
            'created_at'
        );

        return $this->query($request, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
    }

    public function query(Request $request, PaginationParameters $pagination): Builder
    {
        $query = AiModelAccess::query()->withCount('clinics');
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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): AiModelAccess
    {
        $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key']);

        return AiModelAccess::create($this->attributes($data) + [
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiModelAccess $access, array $data, User $actor): AiModelAccess
    {
        $this->validateConfigKeys((string) $data['model_key'], (string) $data['usage_category_key']);

        $access->update($this->attributes($data, $access) + [
            'updated_by' => $actor->id,
        ]);

        return $access->refresh();
    }

    public function toggle(AiModelAccess $access, User $actor): AiModelAccess
    {
        $access->forceFill([
            'is_active' => ! $access->is_active,
            'updated_by' => $actor->id,
        ])->save();

        return $access->refresh();
    }

    public function delete(AiModelAccess $access): void
    {
        if ($access->is_protected) {
            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.protected_delete'),
            ]);
        }

        $access->delete();
    }

    public function resolveForClinic(Tenant $clinic, string $usageCategoryKey, ?int $aiModelAccessId = null): AiModelAccess
    {
        $query = AiModelAccess::query()
            ->active()
            ->where('usage_category_key', $usageCategoryKey)
            ->whereHas('clinics', fn (Builder $query) => $query
                ->where('tenants.id', $clinic->id)
                ->where('clinic_ai_model_access.is_active', true));

        if ($aiModelAccessId) {
            $query->whereKey($aiModelAccessId);
        }

        $access = $query
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

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
     * @return \Illuminate\Database\Eloquent\Collection<int, AiModelAccess>
     */
    public function availableForClinic(Tenant $clinic, string $usageCategoryKey)
    {
        return AiModelAccess::query()
            ->active()
            ->where('usage_category_key', $usageCategoryKey)
            ->whereHas('clinics', fn (Builder $query) => $query
                ->where('tenants.id', $clinic->id)
                ->where('clinic_ai_model_access.is_active', true))
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function validateConfigKeys(string $modelKey, string $usageCategoryKey): void
    {
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
    }

    /**
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
            'settings' => $data['settings'] ?? $access?->settings ?? [],
            'prompt' => $data['prompt'] ?? null,
            'credit_limit' => filled($data['credit_limit'] ?? null) ? (int) $data['credit_limit'] : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => filled($data['priority'] ?? null) ? (int) $data['priority'] : null,
            'is_demo' => (bool) ($data['is_demo'] ?? $access?->is_demo ?? false),
            'is_protected' => (bool) ($data['is_protected'] ?? $access?->is_protected ?? false),
        ];
    }
}
