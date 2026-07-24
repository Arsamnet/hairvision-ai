<?php

namespace App\Services\Visitor;

use App\Models\AiModel;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Services\Billing\ServiceEntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves tenant-visible public AI applications for visitor entrypoints.
 *
 * @since 1.0.0
 */
class PublicApplicationAvailabilityService
{
    /**
     * Create a new public application availability service instance.
     *
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly ServiceEntitlementService $entitlements) {}

    /**
     * Return active requestable categories for the public landing/profile UI.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return Collection<int, AiModelCategory>
     *
     * @since 1.0.0
     */
    public function categoriesForTenant(Tenant $tenant): Collection
    {
        return AiModelCategory::query()
            ->active()
            ->with(['outputs' => fn ($query) => $query->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(function (AiModelCategory $category) use ($tenant): ?AiModelCategory {
                if (! $this->categoryCanBeShown($tenant, $category)) {
                    return null;
                }

                $outputs = $category->outputs
                    ->filter(fn (AiModelCategoryOutput $output): bool => $this->outputHasExecutableModel($tenant, $category, $output))
                    ->values();

                if ($outputs->isEmpty()) {
                    return null;
                }

                $category->setRelation('outputs', $outputs);

                return $category;
            })
            ->filter()
            ->values();
    }

    /**
     * Return dashboard-facing availability summaries for subscription services.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  Subscription|null  $subscription  The subscription value.
     * @return Collection<int, array{service_key: string, label: string, available: bool, status: string, message: string}>
     *
     * @since 1.0.0
     */
    public function summariesForSubscription(Tenant $tenant, ?Subscription $subscription): Collection
    {
        if (! $subscription) {
            return collect();
        }

        $subscription->loadMissing(['plan', 'serviceEntitlements']);

        return $subscription->serviceEntitlements
            ->sortBy('service_key')
            ->map(fn (SubscriptionServiceEntitlement $entitlement): array => $this->summaryForEntitlement($tenant, $subscription, $entitlement))
            ->values();
    }

    /**
     * Require that one public output is currently requestable by this tenant.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  AiModelCategory  $category  The category value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    public function assertCanRequest(Tenant $tenant, AiModelCategory $category, AiModelCategoryOutput $output): void
    {
        if (! $this->categoryCanBeShown($tenant, $category)) {
            throw ValidationException::withMessages([
                'service_access' => __('subscriptions.errors.service_unavailable', ['service' => $category->name]),
            ]);
        }

        if (! $output->is_active || (int) $output->ai_model_category_id !== (int) $category->id) {
            throw ValidationException::withMessages([
                'output_id' => __('messages.generation_output_invalid'),
            ]);
        }

        if (! $this->outputHasExecutableModel($tenant, $category, $output)) {
            throw ValidationException::withMessages([
                'ai_model_access' => __('ai_models.errors.no_eligible_model'),
            ]);
        }
    }

    /**
     * Build the executable model query scoped to a tenant-visible output.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  AiModelCategory  $category  The category value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @return Builder<AiModel>
     *
     * @since 1.0.0
     */
    public function eligibleModelsForOutput(
        Tenant $tenant,
        AiModelCategory $category,
        AiModelCategoryOutput $output
    ): Builder {
        return AiModel::query()
            ->assignable()
            ->where('ai_model_category_id', $category->id)
            ->whereIn(
                'legacy_ai_model_access_id',
                $this->activeAssignedModelAccessQuery($tenant, $category->code)->select('ai_model_accesses.id')
            )
            ->whereHas('outputConfigs', fn (Builder $query) => $query
                ->where('ai_model_category_output_id', $output->id)
                ->where('is_active', true));
    }

    /**
     * Execute the summary for entitlement operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  Subscription  $subscription  The subscription value.
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @return array{service_key: string, label: string, available: bool, status: string, message: string}
     *
     * @since 1.0.0
     */
    private function summaryForEntitlement(
        Tenant $tenant,
        Subscription $subscription,
        SubscriptionServiceEntitlement $entitlement
    ): array {
        $category = AiModelCategory::query()
            ->with(['outputs' => fn ($query) => $query->active()->orderBy('sort_order')])
            ->where('code', $entitlement->service_key)
            ->first();

        $label = $category?->name ?: $entitlement->service_key;
        $status = $this->availabilityStatus($tenant, $subscription, $entitlement, $category);

        return [
            'service_key' => $entitlement->service_key,
            'label' => $label,
            'available' => $status === 'available',
            'status' => $status,
            'message' => __('dashboard.applications.statuses.'.$status),
        ];
    }

    /**
     * Execute the availability status operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  Subscription  $subscription  The subscription value.
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @param  AiModelCategory|null  $category  The category value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function availabilityStatus(
        Tenant $tenant,
        Subscription $subscription,
        SubscriptionServiceEntitlement $entitlement,
        ?AiModelCategory $category
    ): string {
        if ($subscription->plan?->status !== 'active') {
            return 'plan_disabled_by_system';
        }

        if (! $entitlement->is_active) {
            return 'entitlement_inactive';
        }

        if (! $category || $category->status !== 'active') {
            return 'category_disabled_by_system';
        }

        if ($this->entitlements->activeEntitlement($tenant, $category->code) === null) {
            return 'service_unavailable';
        }

        if (! $this->hasActiveAssignedModelAccess($tenant, $category->code)) {
            return $this->modelUnavailableStatus($tenant, $category->code);
        }

        $outputs = $category->outputs;

        if ($outputs->isEmpty()) {
            return 'output_unavailable';
        }

        $hasExecutableOutput = $outputs->contains(fn (AiModelCategoryOutput $output): bool => $this->outputHasExecutableModel($tenant, $category, $output));

        return $hasExecutableOutput ? 'available' : 'executable_model_unavailable';
    }

    /**
     * Execute the category can be shown operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  AiModelCategory  $category  The category value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function categoryCanBeShown(Tenant $tenant, AiModelCategory $category): bool
    {
        return $category->status === 'active'
            && $this->entitlements->activeEntitlement($tenant, $category->code) !== null
            && $this->hasActiveAssignedModelAccess($tenant, $category->code);
    }

    /**
     * Determine whether active assigned model access.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $categoryCode  The category code value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function hasActiveAssignedModelAccess(Tenant $tenant, string $categoryCode): bool
    {
        return $this->activeAssignedModelAccessQuery($tenant, $categoryCode)->exists();
    }

    /**
     * Execute the model unavailable status operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $categoryCode  The category code value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function modelUnavailableStatus(Tenant $tenant, string $categoryCode): string
    {
        $assignedWithActiveClinicPivot = AiModelAccess::query()
            ->where('usage_category_key', $categoryCode)
            ->whereHas('clinics', fn ($query) => $query
                ->where('tenants.id', $tenant->id)
                ->where('clinic_ai_model_access.is_active', true));

        if ((clone $assignedWithActiveClinicPivot)->where('is_active', false)->exists()) {
            return 'model_disabled_by_system';
        }

        if ((clone $assignedWithActiveClinicPivot)->active()->exists()) {
            return 'model_credit_finished';
        }

        return 'clinic_model_unavailable';
    }

    /**
     * Execute the active assigned model access query operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $categoryCode  The category code value.
     * @return Builder<AiModelAccess>
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    private function activeAssignedModelAccessQuery(Tenant $tenant, string $categoryCode): Builder
    {
        return AiModelAccess::query()
            ->active()
            ->where('usage_category_key', $categoryCode)
            ->where(function ($query): void {
                $query
                    ->whereNull('credit_limit')
                    ->orWhereRaw('(credit_used + credit_reserved) < credit_limit');
            })
            ->whereHas('clinics', fn ($query) => $query
                ->where('tenants.id', $tenant->id)
                ->where('clinic_ai_model_access.is_active', true));
    }

    /**
     * Execute the output has executable model operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  AiModelCategory  $category  The category value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    private function outputHasExecutableModel(
        Tenant $tenant,
        AiModelCategory $category,
        AiModelCategoryOutput $output
    ): bool {
        return $this->eligibleModelsForOutput($tenant, $category, $output)->exists();
    }
}
