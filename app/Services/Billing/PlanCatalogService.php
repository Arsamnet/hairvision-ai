<?php

namespace App\Services\Billing;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates and updates purchasable plan definitions with service credit rows.
 *
 * @since 1.0.0
 */
class PlanCatalogService
{
    /**
     * Create a new plan catalog service instance.
     *
     * @param  ServiceCatalog  $services  The services value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly ServiceCatalog $services) {}

    /**
     * Display the form for creating a new plan catalog service.
     *
     * @param  array<string, mixed>  $data
     * @return Plan Resolved operation result.
     *
     * @since 1.0.0
     */
    public function create(array $data): Plan
    {
        $this->resetDebugSteps();

        $scope = 'billing.plan_catalog.create';

        try {
            $plan = DB::transaction(function () use ($data, $scope): Plan {
                $attributes = $this->planAttributes($data, null, $scope);

                $plan = Plan::create($attributes);

                $this->syncServiceCredits($plan, $data, $scope);
                $this->syncModelAccesses($plan, $data);

                $plan->refresh()->load(['serviceEntitlements', 'aiModelAccesses']);

                return $plan;
            });

            return $plan;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Update the requested plan catalog service record.
     *
     * @param  array<string, mixed>  $data
     * @param  Plan  $plan  The plan value.
     * @return Plan Resolved operation result.
     *
     * @since 1.0.0
     */
    public function update(Plan $plan, array $data): Plan
    {
        $this->resetDebugSteps();

        $scope = 'billing.plan_catalog.update';

        try {
            $plan = DB::transaction(function () use ($plan, $data, $scope): Plan {
                $attributes = $this->planAttributes($data, $plan, $scope);

                $plan->update($attributes);

                $this->syncServiceCredits($plan, $data, $scope);
                $this->syncModelAccesses($plan, $data);

                $plan->refresh()->load(['serviceEntitlements', 'aiModelAccesses']);

                return $plan;
            });

            return $plan;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Execute the plan attributes operation.
     *
     * @param  array<string, mixed>  $data
     * @param  Plan|null  $plan  The plan value.
     * @param  string  $scope  The scope value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function planAttributes(array $data, ?Plan $plan = null, string $scope = 'billing.plan_catalog.attributes'): array
    {
        $serviceKeys = $this->normalizedServiceKeys($data);

        $planType = PlanType::from($data['plan_type'] ?? $plan?->plan_type?->value ?? PlanType::BaseSubscription->value);
        $firstService = $serviceKeys[0] ?? ($data['service_key'] ?? $this->services->defaultServiceKey());

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'plan_type' => $planType,
            'service_key' => count($serviceKeys) > 1 ? null : $firstService,
            'price' => (int) $data['price'],
            'currency' => $data['currency'],
            'duration_days' => $planType === PlanType::CreditTopUp ? null : (int) ($data['duration_days'] ?? 0),
            'usage_limit' => (int) ($data['usage_limit'] ?? 0),
            'features_json' => $plan?->features_json ?? [],
            'is_public' => (bool) ($data['is_public'] ?? false),
            'status' => $data['status'],
        ];

        return $attributes;
    }

    /**
     * Synchronize service credits.
     *
     * @param  array<string, mixed>  $data
     * @param  Plan  $plan  The plan value.
     * @param  string  $scope  The scope value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function syncServiceCredits(Plan $plan, array $data, string $scope = 'billing.plan_catalog.service_credits'): void
    {
        $normalized = $this->normalizedPlanEntitlements($plan, $data);
        $keep = [];

        foreach ($normalized as $serviceKey => $credit) {
            $entitlement = $plan->serviceEntitlements()->updateOrCreate(
                ['service_key' => $serviceKey],
                [
                    'credit_amount' => $credit,
                    'is_active' => true,
                ]
            );

            $keep[] = $entitlement->id;

        }

        $deactivatedCount = $plan->serviceEntitlements()
            ->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))
            ->update(['is_active' => false]);

    }

    /**
     * Normalize d plan entitlements.
     *
     * @param  Plan  $plan  The plan value.
     * @param  array  $data  Validated operation data.
     * @return array<string, int>
     *
     * @since 1.0.0
     */
    private function normalizedPlanEntitlements(Plan $plan, array $data): array
    {
        $serviceKeys = $this->normalizedServiceKeys($data);

        if ($serviceKeys === []) {
            $serviceKeys = [$this->services->defaultServiceKey()];
        }

        $usageLimit = (int) $plan->usage_limit;

        return collect($serviceKeys)
            ->mapWithKeys(fn (string $serviceKey, int $index): array => [$serviceKey => $index === 0 ? $usageLimit : 0])
            ->all();
    }

    /**
     * Persist the exact per-application model selections for this plan.
     *
     * @param  array<string, mixed>  $data
     * @param  Plan  $plan  The plan value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function syncModelAccesses(Plan $plan, array $data): void
    {
        $accessIds = $plan->isCreditTopUp()
            ? []
            : collect($data['ai_model_access_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

        $plan->aiModelAccesses()->sync($accessIds);
    }

    /**
     * Normalize d credits.
     *
     * @param  array<string, mixed>  $credits
     * @return array<string, int>
     *
     * @since 1.0.0
     */
    private function normalizedCredits(array $credits): array
    {
        return collect($credits)
            ->mapWithKeys(fn (mixed $credit, string|int $serviceKey): array => [(string) $serviceKey => (int) $credit])
            ->filter(fn (int $credit, string $serviceKey): bool => $credit > 0 && $this->services->has($serviceKey))
            ->all();
    }

    /**
     * Normalize d service keys.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function normalizedServiceKeys(array $data): array
    {
        $keys = collect($data['service_keys'] ?? [])
            ->map(fn (mixed $serviceKey): string => (string) $serviceKey)
            ->filter(fn (string $serviceKey): bool => $this->services->has($serviceKey))
            ->unique()
            ->values();

        if ($keys->isNotEmpty()) {
            return $keys->all();
        }

        $credits = $this->normalizedCredits((array) ($data['service_credits'] ?? []));

        if ($credits !== []) {
            return array_keys($credits);
        }

        $serviceKey = (string) ($data['service_key'] ?? $this->services->defaultServiceKey());

        return $this->services->has($serviceKey) ? [$serviceKey] : [];
    }

    /**
     * Execute the reset debug steps operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function resetDebugSteps(): void {}

    /**
     * Execute the plan context operation.
     *
     * @param  Plan  $plan  The plan value.
     * @return array Resolved data array.
     *
     * @since 1.0.0
     */
    private function planContext(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'plan_type' => $this->planTypeValue($plan->plan_type),
            'service_key' => $plan->service_key,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'duration_days' => $plan->duration_days,
            'usage_limit' => $plan->usage_limit,
            'status' => $plan->status,
            'is_public' => (bool) $plan->is_public,
            'feature_keys' => is_array($plan->features_json) ? array_keys($plan->features_json) : [],
        ];
    }

    /**
     * Execute the plan type value operation.
     *
     * @param  mixed  $planType  The plan type value.
     * @return mixed Resolved operation result.
     *
     * @since 1.0.0
     */
    private function planTypeValue(mixed $planType): mixed
    {
        if ($planType instanceof PlanType) {
            return $planType->value;
        }

        return $planType;
    }

    /**
     * Execute the service credit keys operation.
     *
     * @param  mixed  $credits  The credits value.
     * @return array Resolved data array.
     *
     * @since 1.0.0
     */
    private function serviceCreditKeys(mixed $credits): array
    {
        return is_array($credits) ? array_map('strval', array_keys($credits)) : [];
    }

    /**
     * Execute the service credit count operation.
     *
     * @param  mixed  $credits  The credits value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    private function serviceCreditCount(mixed $credits): int
    {
        return is_array($credits) ? count($credits) : 0;
    }
}
