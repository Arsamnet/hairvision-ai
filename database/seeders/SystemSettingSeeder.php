<?php

namespace Database\Seeders;

use App\Enums\CreditTransactionType;
use App\Enums\PlanAccessStatus;
use App\Enums\PlanType;
use App\Enums\ServiceEntitlementSource;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_merge($this->defaultSettings(), $this->stateCatalogSettings()) as $key => $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'value_json' => ['value' => $setting['value']],
                    'description' => $setting['description'],
                    'is_encrypted' => false,
                ]
            );
        }
    }

    /**
     * @return array<string, array{group: string, label: string, type: string, value: mixed, description: string}>
     */
    private function defaultSettings(): array
    {
        return [
            'system.name' => [
                'group' => 'general',
                'label' => 'نام سیستم',
                'type' => 'string',
                'value' => 'HairVision',
                'description' => 'نام نمایشی در صفحه‌های مدیریتی پلتفرم.',
            ],
            'support.phone' => [
                'group' => 'general',
                'label' => 'شماره پشتیبانی',
                'type' => 'string',
                'value' => '09129999999',
                'description' => 'شماره تماس اصلی پشتیبانی برای کاربران داشبورد.',
            ],
            'notifications.global_enabled' => [
                'group' => 'notifications',
                'label' => 'اعلان‌های سراسری',
                'type' => 'boolean',
                'value' => true,
                'description' => 'ارسال اعلان‌های سطح پلتفرم را کنترل می‌کند.',
            ],
            'billing.invoice_due_days' => [
                'group' => 'billing',
                'label' => 'مهلت پرداخت فاکتور',
                'type' => 'integer',
                'value' => 7,
                'description' => 'مهلت پیش‌فرض پرداخت فاکتورهای ایجاد شده.',
            ],
            'api.pagination.default_per_page' => [
                'group' => 'api',
                'label' => 'تعداد پیش‌فرض هر صفحه API',
                'type' => 'integer',
                'value' => config('hairvision.pagination.default_per_page'),
                'description' => 'تعداد پیش‌فرض آیتم‌ها در صفحه‌بندی‌های مشترک.',
            ],
            'api.pagination.max_per_page' => [
                'group' => 'api',
                'label' => 'حداکثر تعداد هر صفحه API',
                'type' => 'integer',
                'value' => config('hairvision.pagination.max_per_page'),
                'description' => 'حداکثر تعداد قابل قبول برای صفحه‌بندی‌های مشترک.',
            ],
        ];
    }

    /**
     * @return array<string, array{group: string, label: string, type: string, value: mixed, description: string}>
     */
    private function stateCatalogSettings(): array
    {
        return [
            'catalog.states.tenants.statuses' => $this->catalog('وضعیت کلینیک‌ها', [
                'active' => __('system.statuses.active'),
                'inactive' => __('system.statuses.inactive'),
            ]),
            'catalog.states.tenant_domains.types' => $this->catalog('نوع دامنه کلینیک', [
                'tenant_subdomain' => __('clinics.domain_types.tenant_subdomain'),
                'custom_domain' => __('clinics.domain_types.custom_domain'),
                'primary_domain' => __('clinics.domain_types.primary_domain'),
                'platform_subdomain' => __('clinics.domain_types.platform_subdomain'),
            ]),
            'catalog.states.tenant_domains.statuses' => $this->catalog('وضعیت دامنه کلینیک', [
                'active' => __('clinics.statuses.active'),
                'inactive' => __('clinics.statuses.inactive'),
                'pending' => __('clinics.statuses.pending'),
            ]),
            'catalog.states.users.access_scopes' => $this->catalog('محدوده دسترسی کاربران', __('system.user_access_scopes')),
            'catalog.states.users.statuses' => $this->catalog('وضعیت کاربران', [
                'active' => __('system.statuses.active'),
                'inactive' => __('system.statuses.inactive'),
            ]),
            'catalog.states.roles.scopes' => $this->catalog('اسکوپ نقش‌ها', __('system.role_scopes')),
            'catalog.states.roles.statuses' => $this->catalog('وضعیت نقش‌ها', [
                'active' => __('system.statuses.active'),
                'inactive' => __('system.statuses.inactive'),
            ]),
            'catalog.states.leads.statuses' => $this->catalog('وضعیت لیدها', __('leads.statuses')),
            'catalog.states.leads.crm_stages' => $this->catalog('مراحل CRM لیدها', __('leads.crm_stages')),
            'catalog.states.lead_notes.types' => $this->catalog('نوع یادداشت لید', [
                'admin_note' => 'یادداشت مدیر',
                'follow_up' => __('crm.new_follow_up'),
            ]),
            'catalog.states.lead_notes.communication_channels' => $this->catalog('کانال ارتباطی پیگیری', __('crm.channels')),
            'catalog.states.uploads.statuses' => $this->catalog('وضعیت بارگذاری تصویر', [
                'uploaded' => 'بارگذاری شده',
                'validated' => 'اعتبارسنجی شده',
                'rejected' => 'رد شده',
            ]),
            'catalog.states.generation.statuses' => $this->catalog('وضعیت پردازش تصویر', __('ai.generation.statuses')),
            'catalog.states.generation.periods' => $this->catalog('بازه‌های تولید تصویر', __('leads.periods')),
            'catalog.states.ai_providers.types' => $this->catalog('نوع ارائه‌دهنده هوش مصنوعی', [
                'local' => 'محلی',
                'external' => 'خارجی',
            ]),
            'catalog.states.ai_providers.auth_types' => $this->catalog('نوع احراز هویت ارائه‌دهنده', [
                'bearer' => 'Bearer Token',
            ]),
            'catalog.states.ai_model_accesses.models' => $this->catalog('مدل‌های هوش مصنوعی', array_keys(config('ai-models.models', []))),
            'catalog.states.ai_model_accesses.usage_categories' => $this->catalog('کاربردهای مدل هوش مصنوعی', __('ai_models.usage_categories')),
            'catalog.states.ai_model_accesses.statuses' => $this->catalog('وضعیت دسترسی مدل هوش مصنوعی', [
                'active' => __('ai_models.statuses.active'),
                'inactive' => __('ai_models.statuses.inactive'),
            ]),
            'catalog.states.ai_model_usage_logs.statuses' => $this->catalog('وضعیت گزارش مصرف مدل', [
                'started' => __('ai_models.statuses.started'),
                'completed' => __('ai_models.statuses.completed'),
                'failed' => __('ai_models.statuses.failed'),
            ]),
            'catalog.states.sms_gateways.owner_scopes' => $this->catalog('مالک درگاه پیامک', [
                'system' => __('system.owners.system'),
                'tenant' => __('system.owners.tenant'),
            ]),
            'catalog.states.sms_gateways.statuses' => $this->catalog('وضعیت درگاه پیامک', [
                'enabled' => __('system.statuses.enabled'),
                'disabled' => __('system.statuses.disabled'),
            ]),
            'catalog.states.payment_gateways.statuses' => $this->catalog('وضعیت درگاه پرداخت', [
                'enabled' => __('system.statuses.enabled'),
                'disabled' => __('system.statuses.disabled'),
            ]),
            'catalog.states.plans.statuses' => $this->catalog('وضعیت پلن‌ها', __('plans.statuses')),
            'catalog.states.plans.types' => $this->catalog('نوع پلن‌ها', $this->enumValues(PlanType::cases())),
            'catalog.states.invoices.statuses' => $this->catalog('وضعیت فاکتورها', __('payments.invoice_statuses')),
            'catalog.states.payments.statuses' => $this->catalog('وضعیت پرداخت‌ها', __('payments.payment_statuses')),
            'catalog.states.subscriptions.statuses' => $this->catalog('وضعیت اشتراک‌ها', __('payments.subscription_statuses') + ['cancelled' => 'لغو شده']),
            'catalog.states.subscription_service_entitlements.sources' => $this->catalog('منبع اعتبار سرویس اشتراک', $this->enumValues(ServiceEntitlementSource::cases())),
            'catalog.states.credit_transactions.types' => $this->catalog('نوع تراکنش اعتبار', $this->enumValues(CreditTransactionType::cases())),
            'catalog.states.plan_access.statuses' => $this->catalog('وضعیت دسترسی پلن', $this->enumValues(PlanAccessStatus::cases())),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array{group: string, label: string, type: string, value: array<int|string, mixed>, description: string}
     */
    private function catalog(string $label, array $value): array
    {
        return [
            'group' => 'catalog',
            'label' => $label,
            'type' => 'json',
            'value' => $value,
            'description' => 'داده پایه سیستم برای گزینه‌ها و حالت‌های مجاز.',
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, string>
     */
    private function enumValues(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (\BackedEnum $case): array => [$case->value => $case->value])
            ->all();
    }
}
