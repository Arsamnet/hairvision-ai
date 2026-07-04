<?php

namespace App\Services\Billing;

use App\Enums\PlanType;
use App\Models\Plan;
use App\Support\DebugStepLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates and updates purchasable plan definitions with service credit rows.
 *
 * @since 1.0.0
 */
class PlanCatalogService
{
    private int $debugStepCounter = 0;

    public function __construct(private readonly ServiceCatalog $services) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        $this->resetDebugSteps();

        $scope = 'billing.plan_catalog.create';

        $this->debugStep($scope, 'Plan catalog create workflow started.', [
            'data_keys' => array_keys($data),
            'service_credit_keys' => $this->serviceCreditKeys($data['service_credits'] ?? []),
            'service_credit_count' => $this->serviceCreditCount($data['service_credits'] ?? []),
            'has_service_credits' => ! empty($data['service_credits'] ?? []),
        ]);

        try {
            $plan = DB::transaction(function () use ($data, $scope): Plan {
                $attributes = $this->planAttributes($data, null, $scope);

                $this->debugStep($scope, 'Plan attributes prepared for create.', [
                    'attribute_keys' => array_keys($attributes),
                    'plan_type' => $this->planTypeValue($attributes['plan_type'] ?? null),
                    'service_key' => $attributes['service_key'] ?? null,
                    'usage_limit' => $attributes['usage_limit'] ?? null,
                    'currency' => $attributes['currency'] ?? null,
                    'status' => $attributes['status'] ?? null,
                ]);

                $plan = Plan::create($attributes);

                $this->debugStep($scope, 'Plan record created.', [
                    'plan' => $this->planContext($plan),
                    'was_recently_created' => $plan->wasRecentlyCreated,
                ]);

                $this->syncServiceCredits($plan, $data['service_credits'] ?? [], $scope);

                $plan->refresh();

                $this->debugStep($scope, 'Plan catalog create workflow completed inside transaction.', [
                    'plan' => $this->planContext($plan),
                ]);

                return $plan;
            });

            $this->debugStep($scope, 'Plan catalog create workflow completed successfully.', [
                'plan' => $this->planContext($plan),
            ]);

            return $plan;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Plan catalog create workflow failed.', [
                'data_keys' => array_keys($data),
                'service_credit_keys' => $this->serviceCreditKeys($data['service_credits'] ?? []),
                'exception_class' => $exception::class,
            ]);

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

        $this->debugStep($scope, 'Plan catalog update workflow started.', [
            'plan' => $this->planContext($plan),
            'data_keys' => array_keys($data),
            'service_credit_keys' => $this->serviceCreditKeys($data['service_credits'] ?? []),
            'service_credit_count' => $this->serviceCreditCount($data['service_credits'] ?? []),
            'has_service_credits' => ! empty($data['service_credits'] ?? []),
        ]);

        try {
            $plan = DB::transaction(function () use ($plan, $data, $scope): Plan {
                $attributes = $this->planAttributes($data, $plan, $scope);

                $this->debugStep($scope, 'Plan attributes prepared for update.', [
                    'plan_id' => $plan->id,
                    'attribute_keys' => array_keys($attributes),
                    'plan_type' => $this->planTypeValue($attributes['plan_type'] ?? null),
                    'service_key' => $attributes['service_key'] ?? null,
                    'usage_limit' => $attributes['usage_limit'] ?? null,
                    'currency' => $attributes['currency'] ?? null,
                    'status' => $attributes['status'] ?? null,
                ]);

                $plan->update($attributes);

                $this->debugStep($scope, 'Plan record updated.', [
                    'plan' => $this->planContext($plan),
                ]);

                $this->syncServiceCredits($plan, $data['service_credits'] ?? [], $scope);

                $plan->refresh();

                $this->debugStep($scope, 'Plan catalog update workflow completed inside transaction.', [
                    'plan' => $this->planContext($plan),
                ]);

                return $plan;
            });

            $this->debugStep($scope, 'Plan catalog update workflow completed successfully.', [
                'plan' => $this->planContext($plan),
            ]);

            return $plan;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Plan catalog update workflow failed.', [
                'plan_id' => $plan->id,
                'data_keys' => array_keys($data),
                'service_credit_keys' => $this->serviceCreditKeys($data['service_credits'] ?? []),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planAttributes(array $data, ?Plan $plan = null, string $scope = 'billing.plan_catalog.attributes'): array
    {
        $this->debugStep($scope, 'Plan attribute normalization started.', [
            'plan_id' => $plan?->id,
            'data_keys' => array_keys($data),
            'input_service_credit_keys' => $this->serviceCreditKeys($data['service_credits'] ?? []),
        ]);

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
            'status' => $data['status'],
        ];

        $this->debugStep($scope, 'Plan attribute normalization completed.', [
            'plan_id' => $plan?->id,
            'plan_type' => $planType->value,
            'service_key' => $attributes['service_key'],
            'normalized_service_credit_keys' => array_keys($credits),
            'normalized_service_credit_count' => count($credits),
            'usage_limit' => $attributes['usage_limit'],
            'duration_days' => $attributes['duration_days'],
            'currency' => $attributes['currency'],
            'status' => $attributes['status'],
            'has_description' => filled($attributes['description']),
            'feature_keys' => is_array($attributes['features_json']) ? array_keys($attributes['features_json']) : [],
        ]);

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $credits
     */
    private function syncServiceCredits(Plan $plan, array $credits, string $scope = 'billing.plan_catalog.service_credits'): void
    {
        $this->debugStep($scope, 'Plan service credit sync started.', [
            'plan_id' => $plan->id,
            'input_service_credit_keys' => $this->serviceCreditKeys($credits),
            'input_service_credit_count' => $this->serviceCreditCount($credits),
        ]);

        $normalized = $this->normalizedCredits($credits);
        $keep = [];

        $this->debugStep($scope, 'Plan service credits normalized.', [
            'plan_id' => $plan->id,
            'normalized_service_credit_keys' => array_keys($normalized),
            'normalized_service_credit_count' => count($normalized),
            'normalized_credit_sum' => array_sum($normalized),
        ]);

        foreach ($normalized as $serviceKey => $credit) {
            $entitlement = $plan->serviceEntitlements()->updateOrCreate(
                ['service_key' => $serviceKey],
                [
                    'credit_amount' => $credit,
                    'is_active' => true,
                ]
            );

            $keep[] = $entitlement->id;

            $this->debugStep($scope, 'Plan service entitlement synced.', [
                'plan_id' => $plan->id,
                'service_entitlement_id' => $entitlement->id,
                'service_key' => $serviceKey,
                'credit_amount' => $credit,
                'is_active' => (bool) $entitlement->is_active,
                'was_recently_created' => $entitlement->wasRecentlyCreated,
            ]);
        }

        $deactivatedCount = $plan->serviceEntitlements()
            ->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))
            ->update(['is_active' => false]);

        $this->debugStep($scope, 'Stale plan service entitlements deactivated.', [
            'plan_id' => $plan->id,
            'kept_entitlement_ids' => $keep,
            'kept_entitlement_count' => count($keep),
            'deactivated_count' => $deactivatedCount,
        ]);
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

    private function resetDebugSteps(): void
    {
        $this->debugStepCounter = 0;
    }

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }

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