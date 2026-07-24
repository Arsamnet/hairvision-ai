<?php

namespace App\Services\Notifications;

use App\Models\AiModelCategory;
use App\Models\ClinicNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates clinic notifications and snapshots their recipient tenants.
 *
 * @since 1.0.0
 */
class ClinicNotificationService
{
    /**
     * Create and deliver.
     *
     * @param  array<string, mixed>  $data
     * @param  User  $user  Authenticated user performing the operation.
     * @return ClinicNotification Resolved operation result.
     *
     * @since 1.0.0
     */
    public function createAndDeliver(array $data, User $user): ClinicNotification
    {
        return DB::transaction(function () use ($data, $user): ClinicNotification {
            $rules = $this->targetingRules($data);
            $tenants = $this->resolveTenants($rules);

            $notification = ClinicNotification::query()->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'body_html' => $data['body_html'],
                'type' => $data['type'],
                'importance' => $data['importance'],
                'status' => 'published',
                'target_scope' => $rules['scope'],
                'targeting_rules' => $rules,
                'visible_from' => $data['visible_from'] ?? null,
                'visible_until' => $data['visible_until'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $now = now();

            $notification->recipients()->createMany(
                $tenants->map(fn (Tenant $tenant): array => [
                    'tenant_id' => $tenant->id,
                    'delivered_at' => $now,
                ])->all()
            );

            return $notification;
        });
    }

    /**
     * Execute the targeting rules operation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function targetingRules(array $data): array
    {
        $scope = (string) $data['target_scope'];

        if ($scope === 'tenant') {
            $tenantIds = array_values(array_filter(array_map('intval', (array) ($data['target_tenant_ids'] ?? [$data['target_tenant_id'] ?? null]))));

            return [
                'scope' => 'tenant',
                'tenant_ids' => array_slice($tenantIds, 0, 1),
                ...$this->filterRules($data),
            ];
        }

        if ($scope === 'selected') {
            return [
                'scope' => 'selected',
                'tenant_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($data['target_tenant_ids'] ?? []))))),
                ...$this->filterRules($data),
            ];
        }

        return [
            'scope' => 'all',
            ...$this->filterRules($data),
        ];
    }

    /**
     * Resolve tenants.
     *
     * @param  array<string, mixed>  $rules
     * @return Collection<int, Tenant>
     *
     * @since 1.0.0
     */
    public function resolveTenants(array $rules): Collection
    {
        $query = Tenant::query()->where('status', 'active');

        if (in_array(($rules['scope'] ?? 'all'), ['tenant', 'selected'], true)) {
            $query->whereKey($rules['tenant_ids'] ?? []);
        }

        if (($rules['plan_state'] ?? 'any') === 'active') {
            $query->whereHas('subscriptions', function (Builder $query): void {
                $query
                    ->where('status', 'active')
                    ->where('ends_at', '>', JalaliDate::nowDateTimeString());
            });
        } elseif (($rules['plan_state'] ?? 'any') === 'inactive') {
            $query->whereDoesntHave('subscriptions', function (Builder $query): void {
                $query
                    ->where('status', 'active')
                    ->where('ends_at', '>', JalaliDate::nowDateTimeString());
            });
        }

        if (! empty($rules['ai_model_category_id'])) {
            $category = AiModelCategory::query()->find((int) $rules['ai_model_category_id']);

            if ($category instanceof AiModelCategory) {
                $query->whereHas('serviceEntitlements', function (Builder $query) use ($category): void {
                    $query
                        ->where('service_key', $category->code)
                        ->where('is_active', true)
                        ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', JalaliDate::nowDateTimeString()));
                });
            }
        }

        if (! empty($rules['ai_model_access_id'])) {
            $query->whereHas('aiModelAccesses', function (Builder $query) use ($rules): void {
                $query
                    ->where('ai_model_accesses.id', (int) $rules['ai_model_access_id'])
                    ->where('ai_model_accesses.is_active', true)
                    ->where('clinic_ai_model_access.is_active', true);
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Execute the filter rules operation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function filterRules(array $data): array
    {
        return [
            'plan_state' => (string) ($data['filter_plan_state'] ?? 'any'),
            'ai_model_category_id' => filled($data['filter_ai_model_category_id'] ?? null) ? (int) $data['filter_ai_model_category_id'] : null,
            'ai_model_access_id' => filled($data['filter_ai_model_access_id'] ?? null) ? (int) $data['filter_ai_model_access_id'] : null,
        ];
    }

    /**
     * Return the number of active unread messages in a clinic inbox.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function unreadCountForTenant(Tenant $tenant): int
    {
        return $tenant->id
            ? (int) $tenant->notificationRecipients()->unread()->currentlyVisible()->count()
            : 0;
    }
}
