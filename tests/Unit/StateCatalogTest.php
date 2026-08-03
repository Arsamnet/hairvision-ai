<?php

namespace Tests\Unit;

use App\Enums\CreditTransactionType;
use App\Enums\PlanAccessStatus;
use App\Enums\PlanType;
use App\Enums\ServiceEntitlementSource;
use App\Support\StateCatalog;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Verifies code-owned status catalogs and their localized labels.
 *
 * @since 2.0.0
 * @since 2.1.0 Coverage expanded for the unified lead status catalog.
 */
class StateCatalogTest extends TestCase
{
    public function test_state_catalog_resolves_global_config_to_localized_options(): void
    {
        $this->assertSame(__('leads.statuses'), StateCatalog::options('leads.statuses'));
        $this->assertSame(
            ['new', 'needs_follow_up', 'no_answer', 'successful', 'canceled'],
            StateCatalog::values('leads.statuses')
        );
        $this->assertSame(__('plans.types'), StateCatalog::options('plans.types'));
        $this->assertSame(__('payments.invoice_statuses'), StateCatalog::options('invoices.statuses'));

        $this->assertSame(
            array_map(fn (PlanType $type): string => $type->value, PlanType::cases()),
            StateCatalog::values('plans.types')
        );
        $this->assertSame(
            array_map(fn (ServiceEntitlementSource $source): string => $source->value, ServiceEntitlementSource::cases()),
            StateCatalog::values('subscription_service_entitlements.sources')
        );
        $this->assertSame(
            array_map(fn (CreditTransactionType $type): string => $type->value, CreditTransactionType::cases()),
            StateCatalog::values('credit_transactions.types')
        );
        $this->assertSame(
            array_map(fn (PlanAccessStatus $status): string => $status->value, PlanAccessStatus::cases()),
            StateCatalog::values('plan_access.statuses')
        );
    }

    public function test_every_configured_state_has_a_translation(): void
    {
        $catalogs = [
            'tenants.statuses',
            'tenant_domains.types',
            'tenant_domains.statuses',
            'users.access_scopes',
            'users.statuses',
            'roles.scopes',
            'roles.statuses',
            'leads.statuses',
            'lead_notes.types',
            'lead_notes.communication_channels',
            'uploads.statuses',
            'generation.statuses',
            'generation.periods',
            'ai_providers.types',
            'ai_providers.auth_types',
            'ai_model_accesses.statuses',
            'ai_model_usage_logs.statuses',
            'sms_gateways.owner_scopes',
            'sms_gateways.statuses',
            'payment_gateways.statuses',
            'plans.statuses',
            'plans.types',
            'invoices.statuses',
            'payments.statuses',
            'subscriptions.statuses',
            'subscription_service_entitlements.sources',
            'credit_transactions.types',
            'plan_access.statuses',
        ];

        foreach ($catalogs as $catalog) {
            $definitions = config('state-catalog.'.$catalog);

            $this->assertIsArray($definitions, $catalog);
            $this->assertNotEmpty($definitions, $catalog);

            foreach ($definitions as $translationKey) {
                $this->assertTrue(Lang::has($translationKey), $translationKey);
            }
        }
    }
}
