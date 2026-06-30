<?php

namespace App\Services\Billing;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data): Plan {
            $plan = Plan::create($this->planAttributes($data));
            $this->syncServiceCredits($plan, $data['service_credits'] ?? []);

            return $plan->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data): Plan {
            $plan->update($this->planAttributes($data, $plan));
            $this->syncServiceCredits($plan, $data['service_credits'] ?? []);

            return $plan->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planAttributes(array $data, ?Plan $plan = null): array
    {
        $credits = $this->normalizedCredits($data['service_credits'] ?? []);
        $planType = PlanType::from($data['plan_type'] ?? $plan?->plan_type?->value ?? PlanType::BaseSubscription->value);
        $firstService = array_key_first($credits) ?: ($data['service_key'] ?? $this->services->defaultServiceKey());

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'plan_type' => $planType,
            'service_key' => $planType === PlanType::BaseSubscription && count($credits) > 1 ? null : $firstService,
            'price' => (int) $data['price'],
            'currency' => $data['currency'],
            'duration_days' => $planType === PlanType::CreditTopUp ? null : (int) ($data['duration_days'] ?? 0),
            'usage_limit' => array_sum($credits),
            'features_json' => $plan?->features_json ?? [],
            'status' => $data['status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $credits
     */
    private function syncServiceCredits(Plan $plan, array $credits): void
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

        $plan->serviceEntitlements()
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
}
