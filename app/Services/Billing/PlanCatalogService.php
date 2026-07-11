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
    public function __construct(private readonly ServiceCatalog $services) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        $this->resetDebugSteps();

        $scope = 'billing.plan_catalog.create';

        try {
            $plan = DB::transaction(function () use ($data, $scope): Plan {
                $attributes = $this->planAttributes($data, null, $scope);

                $plan = Plan::create($attributes);

                $this->syncServiceCredits($plan, $data['service_credits'] ?? [], $scope);

                $plan->refresh();

                return $plan;
            });

            return $plan;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $this->resetDebugSteps();

        $scope = 'billing.plan_catalog.update';

        try {
            $plan = DB::transaction(function () use ($plan, $data, $scope): Plan {
                $attributes = $this->planAttributes($data, $plan, $scope);

                $plan->update($attributes);

                $this->syncServiceCredits($plan, $data['service_credits'] ?? [], $scope);

                $plan->refresh();

                return $plan;
            });

            return $plan;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planAttributes(array $data, ?Plan $plan = null, string $scope = 'billing.plan_catalog.attributes'): array
    {
        $credits = $this->normalizedCredits($data['service_credits'] ?? []);

        $planType = PlanType::from($data['plan_type'] ?? $plan?->plan_type?->value ?? PlanType::BaseSubscription->value);
        $firstService = array_key_first($credits) ?: ($data['service_key'] ?? $this->services->defaultServiceKey());

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'plan_type' => $planType,
            'service_key' => $planType === PlanType::BaseSubscription && count($credits) > 1 ? null : $firstService,
            'price' => (int) $data['price'],
            'currency' => $data['currency'],
            'duration_days' => $planType === PlanType::CreditTopUp ? null : (int) ($data['duration_days'] ?? 0),
            'usage_limit' => array_sum($credits),
            'features_json' => $plan?->features_json ?? [],
            'is_public' => (bool) ($data['is_public'] ?? false),
            'status' => $data['status'],
        ];

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $credits
     */
    private function syncServiceCredits(Plan $plan, array $credits, string $scope = 'billing.plan_catalog.service_credits'): void
    {
        $normalized = $this->normalizedCredits($credits);
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
     * @param  array<string, mixed>  $credits
     * @return array<string, int>
     */
    private function normalizedCredits(array $credits): array
    {
        return collect($credits)
            ->mapWithKeys(fn (mixed $credit, string|int $serviceKey): array => [(string) $serviceKey => (int) $credit])
            ->filter(fn (int $credit, string $serviceKey): bool => $credit > 0 && $this->services->has($serviceKey))
            ->all();
    }

    private function resetDebugSteps(): void {}

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

    private function planTypeValue(mixed $planType): mixed
    {
        if ($planType instanceof PlanType) {
            return $planType->value;
        }

        return $planType;
    }

    private function serviceCreditKeys(mixed $credits): array
    {
        return is_array($credits) ? array_map('strval', array_keys($credits)) : [];
    }

    private function serviceCreditCount(mixed $credits): int
    {
        return is_array($credits) ? count($credits) : 0;
    }
}
