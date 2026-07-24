<?php

namespace App\Services\AI;

use App\Actions\Ai\AssignAiModelToClinicAction;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Ensures purchased AI applications have an executable clinic model access.
 *
 * Plan-selected model accesses are validated, snapshotted on invoices, and
 * assigned idempotently after successful activation.
 *
 * @since 1.0.0
 */
class ClinicAiModelProvisioningService
{
    /**
     * Create a new clinic ai model provisioning service instance.
     *
     * @param  AssignAiModelToClinicAction  $assign  The assign value.
     * @param  AiModelResolver  $resolver  The resolver value.
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly AssignAiModelToClinicAction $assign,
        private readonly AiModelResolver $resolver,
    ) {}

    /**
     * Return model accesses that can be selected in plan management forms.
     *
     * @param  array<int, string>|null  $serviceKeys
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    public function assignableAccesses(?array $serviceKeys = null): Collection
    {
        $query = $this->candidateQuery();

        if ($serviceKeys !== null) {
            $query->whereIn('usage_category_key', $this->normalizedServiceKeys($serviceKeys));
        }

        $accesses = $query->get();
        $requiredOutputIds = $accesses
            ->pluck('usage_category_key')
            ->unique()
            ->mapWithKeys(fn (string $serviceKey): array => [
                $serviceKey => $this->requiredOutputIds($serviceKey),
            ]);

        return $accesses
            ->filter(function (AiModelAccess $access) use ($requiredOutputIds): bool {
                $required = $requiredOutputIds->get($access->usage_category_key, []);

                return $required !== []
                    && $this->uncoveredOutputIds($required, collect([$access])) === [];
            })
            ->values();
    }

    /**
     * Validate model ids submitted while defining a plan.
     *
     * @param  array<int, string>  $serviceKeys
     * @param  array<int, int|string>  $accessIds
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    public function assertSelectionProvisionable(array $serviceKeys, array $accessIds): void
    {
        try {
            $this->resolveSelection($serviceKeys, $accessIds);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'ai_model_access_ids' => collect($exception->errors())->flatten()->first()
                    ?? __('plans.validation.model_selection_invalid'),
            ]);
        }
    }

    /**
     * Reject checkout when the models explicitly attached to a plan cannot run.
     *
     * @param  array<int, int|string>|null  $accessIds  Invoice-time snapshot.
     * @param  Plan  $plan  The plan value.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    public function assertPlanProvisionable(Plan $plan, ?array $accessIds = null): void
    {
        if ($plan->isCreditTopUp()) {
            return;
        }

        $this->modelAccessesForPlan($plan, $accessIds);
    }

    /**
     * Return the stable model ids that must be saved on a new invoice.
     *
     * @param  Plan  $plan  The plan value.
     * @return array<int, int>
     *
     * @since 1.0.0
     */
    public function modelAccessIdsForPlan(Plan $plan): array
    {
        if ($plan->isCreditTopUp()) {
            return [];
        }

        return $this->modelAccessesForPlan($plan)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Idempotently assign exactly the plan-selected models to the clinic.
     *
     * @param  array<int, int|string>|null  $accessIds  Invoice-time snapshot.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  Plan  $plan  The plan value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    public function provisionPlan(
        Tenant $clinic,
        Plan $plan,
        ?User $actor = null,
        ?array $accessIds = null,
    ): void {
        if ($plan->isCreditTopUp()) {
            return;
        }

        $this->modelAccessesForPlan($plan, $accessIds)
            ->each(fn (AiModelAccess $access) => $this->assign->execute($clinic, $access, $actor));
    }

    /**
     * Return system models that are currently safe to attach to a clinic.
     *
     * @param  string|null  $serviceKey  The service key value.
     * @return Builder<AiModelAccess>
     *
     * @since 1.0.0
     */
    private function candidateQuery(?string $serviceKey = null): Builder
    {
        return AiModelAccess::query()
            ->with(['executableModel.outputConfigs' => fn ($query) => $query->where('is_active', true)])
            ->active()
            ->when($serviceKey !== null, fn (Builder $query) => $query->where('usage_category_key', $serviceKey))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('credit_limit')
                    ->orWhereColumn('credit_used', '<', 'credit_limit');
            })
            ->whereHas('executableModel', fn (Builder $query) => $query
                ->assignable()
                ->whereHas('category', fn (Builder $query) => $query
                    ->when(
                        $serviceKey !== null,
                        fn (Builder $query) => $query->where('code', $serviceKey),
                        fn (Builder $query) => $query->whereColumn('ai_model_categories.code', 'ai_model_accesses.usage_category_key'),
                    )
                    ->where('status', 'active'))
                ->whereHas('outputConfigs', fn (Builder $query) => $query->where('is_active', true)))
            ->orderByRaw('priority is null')
            ->orderBy('priority')
            ->orderBy('id');
    }

    /**
     * Execute the model accesses for plan operation.
     *
     * @param  array<int, int|string>|null  $accessIds
     * @param  Plan  $plan  The plan value.
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    private function modelAccessesForPlan(Plan $plan, ?array $accessIds = null): Collection
    {
        $serviceKeys = $plan->serviceKeys();

        if ($accessIds !== null) {
            $requiresOneModelPerService = $plan->aiModelAccesses()->exists();

            return $this->resolveSelection($serviceKeys, $accessIds, $requiresOneModelPerService);
        }

        $configuredIds = $plan->aiModelAccesses()
            ->pluck('ai_model_accesses.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($configuredIds !== []) {
            return $this->resolveSelection($serviceKeys, $configuredIds);
        }

        // Plans created before explicit model assignment remain purchasable.
        return $this->legacyFallbackSelection($serviceKeys);
    }

    /**
     * Resolve selection.
     *
     * @param  array<int, string>  $serviceKeys
     * @param  array<int, int|string>  $accessIds
     * @param  bool  $requiresOneModelPerService  False only for legacy plans without an explicit mapping.
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    private function resolveSelection(
        array $serviceKeys,
        array $accessIds,
        bool $requiresOneModelPerService = true,
    ): Collection {
        $serviceKeys = $this->normalizedServiceKeys($serviceKeys);
        $accessIds = $this->normalizedAccessIds($accessIds);

        if ($requiresOneModelPerService && count($accessIds) !== count($serviceKeys)) {
            $this->throwOneModelPerService();
        }

        $accesses = $this->candidateQuery()
            ->whereIn('ai_model_accesses.id', $accessIds)
            ->get();

        if ($accessIds === [] || $accesses->count() !== count($accessIds)) {
            throw ValidationException::withMessages([
                'plan' => __('plans.validation.model_selection_invalid'),
            ]);
        }

        if ($accesses->contains(fn (AiModelAccess $access): bool => ! in_array($access->usage_category_key, $serviceKeys, true))) {
            throw ValidationException::withMessages([
                'plan' => __('plans.validation.model_service_mismatch'),
            ]);
        }

        if ($requiresOneModelPerService) {
            $selectedCounts = $accesses->countBy('usage_category_key');

            if (collect($serviceKeys)->contains(
                fn (string $serviceKey): bool => $selectedCounts->get($serviceKey, 0) !== 1
            )) {
                $this->throwOneModelPerService();
            }
        }

        $this->assertOutputCoverage($serviceKeys, $accesses);

        return $accesses;
    }

    /**
     * Execute the legacy fallback selection operation.
     *
     * @param  array<int, string>  $serviceKeys
     * @return Collection<int, AiModelAccess>
     *
     * @since 1.0.0
     */
    private function legacyFallbackSelection(array $serviceKeys): Collection
    {
        $selected = collect();

        foreach ($this->normalizedServiceKeys($serviceKeys) as $serviceKey) {
            $requiredOutputIds = $this->requiredOutputIds($serviceKey);
            $candidates = $this->candidateQuery($serviceKey)->get();
            $remainingOutputIds = $requiredOutputIds;

            foreach ($candidates as $candidate) {
                if ($this->coveredOutputIds($candidate, $remainingOutputIds) === []) {
                    continue;
                }

                $selected->push($candidate);
                $remainingOutputIds = $this->uncoveredOutputIds($remainingOutputIds, collect([$candidate]));

                if ($remainingOutputIds === []) {
                    break;
                }
            }

            if ($requiredOutputIds === [] || $remainingOutputIds !== []) {
                $this->throwUnavailable($serviceKey);
            }
        }

        return $selected->unique('id')->values();
    }

    /**
     * Execute the assert output coverage operation.
     *
     * @param  array<int, string>  $serviceKeys
     * @param  Collection<int, AiModelAccess>  $accesses
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function assertOutputCoverage(array $serviceKeys, Collection $accesses): void
    {
        foreach ($serviceKeys as $serviceKey) {
            $requiredOutputIds = $this->requiredOutputIds($serviceKey);
            $serviceAccesses = $accesses->where('usage_category_key', $serviceKey)->values();

            if ($requiredOutputIds === [] || $this->uncoveredOutputIds($requiredOutputIds, $serviceAccesses) !== []) {
                $this->throwUnavailable($serviceKey);
            }
        }
    }

    /**
     * Execute the required output ids operation.
     *
     * @param  string  $serviceKey  The service key value.
     * @return array<int, int>
     *
     * @since 1.0.0
     */
    private function requiredOutputIds(string $serviceKey): array
    {
        return AiModelCategory::query()
            ->active()
            ->where('code', $serviceKey)
            ->first()
            ?->outputs()
            ->active()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all() ?? [];
    }

    /**
     * Execute the uncovered output ids operation.
     *
     * @param  array<int, int>  $requiredOutputIds
     * @param  Collection<int, AiModelAccess>  $accesses
     * @return array<int, int>
     *
     * @since 1.0.0
     */
    private function uncoveredOutputIds(array $requiredOutputIds, Collection $accesses): array
    {
        $covered = $accesses
            ->flatMap(fn (AiModelAccess $access): array => $this->coveredOutputIds($access, $requiredOutputIds))
            ->unique()
            ->all();

        return array_values(array_diff($requiredOutputIds, $covered));
    }

    /**
     * Execute the covered output ids operation.
     *
     * @param  array<int, int>  $requiredOutputIds
     * @param  AiModelAccess  $access  The access value.
     * @return array<int, int>
     *
     * @since 1.0.0
     */
    private function coveredOutputIds(AiModelAccess $access, array $requiredOutputIds): array
    {
        return $access->executableModel?->outputConfigs
            ->pluck('ai_model_category_output_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->intersect($requiredOutputIds)
            ->values()
            ->all() ?? [];
    }

    /**
     * Normalize d service keys.
     *
     * @param  array<int, string>  $serviceKeys
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function normalizedServiceKeys(array $serviceKeys): array
    {
        return collect($serviceKeys)
            ->map(fn (mixed $serviceKey): string => trim((string) $serviceKey))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normalize d access ids.
     *
     * @param  array<int, int|string>  $accessIds
     * @return array<int, int>
     *
     * @since 1.0.0
     */
    private function normalizedAccessIds(array $accessIds): array
    {
        return collect($accessIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Execute the throw unavailable operation.
     *
     * @param  string  $serviceKey  The service key value.
     * @return never Resolved operation result.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    private function throwUnavailable(string $serviceKey): never
    {
        throw ValidationException::withMessages([
            'plan' => __('subscriptions.errors.service_model_unavailable', [
                'service' => $this->resolver->usageCategoryLabel($serviceKey),
            ]),
        ]);
    }

    /**
     * Execute the throw one model per service operation.
     *
     * @return never Resolved operation result.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    private function throwOneModelPerService(): never
    {
        throw ValidationException::withMessages([
            'plan' => __('plans.validation.one_model_per_service'),
        ]);
    }
}
