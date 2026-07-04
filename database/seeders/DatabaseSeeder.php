<?php

namespace Database\Seeders;

use App\Models\AiModelAccess;
use App\Models\AiProvider;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\SystemSetting;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\User;
use App\Services\Auth\PermissionCatalog;
use App\Services\Auth\PermissionService;
use App\Services\Billing\SubscriptionActivationService;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const DEFAULT_IPPANEL_AUTHORIZATION_TOKEN = 'YTIxMzVhYmMtZGRkYS00OWJhLWE2ZTItNjUzYjhkYTg5M2U0OGRiZjZjMzZiNjBkNjgxYmUwZTAwM2IwZDc1MzczMTU=';

    private const DEFAULT_ZARINPAL_SANDBOX_MERCHANT_ID = '11111111-1111-4111-8111-111111111111';

    // Allow model events so model boot methods (like UUID generation) run during seeding.

    public function run(): void
    {
        $this->seedAccessControl();
        $this->seedSystemDefaults();

        $superAdmin = $this->upsertUser(
            config('hairvision.super_admin.name'),
            config('hairvision.super_admin.email'),
            config('hairvision.super_admin.phone'),
            'super_admin'
        );

        $admin = $this->upsertUser(
            'مدیر کلینیک آریا',
            'test@example.com',
            '09217359491',
            'clinic_admin'
        );

        $editor = $this->upsertUser(
            'کارشناس پذیرش آریا',
            'editor@example.com',
            '09120000001',
            'editor'
        );

        $viewer = $this->upsertUser(
            'گزارش‌گیر آریا',
            'viewer@example.com',
            '09120000002',
            'viewer'
        );

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'aria'],
            [
                'name' => 'کلینیک آریا',
                'status' => 'active',
            ]
        );

        // Visitor-facing tenant domain
        TenantDomain::query()->updateOrCreate(
            ['domain' => 'aria.hairvision-ai.test'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'tenant_subdomain',
                'status' => 'active',
                'ssl_status' => 'unknown',
            ]
        );

        // Platform admin domain
        TenantDomain::query()->updateOrCreate(
            ['domain' => 'hairvision-ai.test'],
            [
                'tenant_id' => $tenant->id,
                'type' => 'platform',
                'status' => 'active',
                'ssl_status' => 'unknown',
            ]
        );

        $provider = AiProvider::query()->updateOrCreate(
            ['code' => 'sample_image'],
            [
                'name' => __('messages.sample_image_provider'),
                'type' => 'local',
                'is_active' => false,
                'supports_async' => false,
            ]
        );

        AiProvider::query()->updateOrCreate(
            ['code' => 'gapgpt'],
            [
                'name' => __('messages.gapgpt_image_provider'),
                'type' => 'external',
                'base_url' => config('ai-models.models.gapgpt.endpoint'),
                'auth_type' => 'bearer',
                'is_active' => true,
                'supports_async' => false,
                'default_model' => config('ai-models.models.gapgpt.default_model'),
            ]
        );

        TenantAiSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'provider_id' => $provider->id],
            ['is_default' => true]
        );

        $demoAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => __('ai_models.seed.demo_access_name'),
            ],
            [
                'description' => __('ai_models.seed.demo_access_description'),
                'credentials' => [],
                'settings' => [],
                'prompt' => __('ai_models.seed.demo_prompt'),
                'credit_limit' => null,
                'credit_used' => 0,
                'is_active' => true,
                'is_demo' => true,
                'is_protected' => true,
                'priority' => 1,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $gapGptApiKey = config('services.gapgpt.api_key');
        $gapGptIsActive = filled($gapGptApiKey);
        $gapGptAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'hair_transplant',
                'name' => __('ai_models.seed.gapgpt_access_name'),
            ],
            [
                'description' => __('ai_models.seed.gapgpt_access_description'),
                'credentials' => filled($gapGptApiKey) ? ['api_key' => $gapGptApiKey] : [],
                'settings' => [
                    'endpoint_url' => config('services.gapgpt.image_edit_endpoint'),
                    'model' => config('services.gapgpt.image_model'),
                    'size' => config('services.gapgpt.image_size'),
                    'response_format' => config('services.gapgpt.image_response_format'),
                    'timeout_seconds' => config('ai-models.models.gapgpt.default_timeout'),
                    'connect_timeout_seconds' => config('ai-models.models.gapgpt.default_connect_timeout'),
                    'retry_attempts' => 1,
                    'retry_sleep_ms' => 750,
                    'stage_prompts' => $this->gapGptStagePrompts(),
                ],
                'prompt' => '{stage_prompt}',
                'credit_limit' => null,
                'credit_used' => 0,
                'is_active' => $gapGptIsActive,
                'is_demo' => false,
                'is_protected' => false,
                'priority' => $gapGptIsActive ? 0 : 10,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $demoAccess->id => [
                'is_active' => true,
                'assigned_by' => $superAdmin->id,
            ],
            $gapGptAccess->id => [
                'is_active' => $gapGptIsActive,
                'assigned_by' => $superAdmin->id,
            ],
        ]);

        $template = Template::query()->updateOrCreate(
            ['code' => 'classic'],
            [
                'name' => __('messages.classic_template'),
                'is_active' => true,
            ]
        );

        TenantThemeSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'template_id' => $template->id,
                'favicon_path' => null,
                'primary_color' => '#0f766e',
                'hero_title' => 'پیش نمایش هوشمند مسیر کاشت مو',
                'hero_subtitle' => 'با تایید شماره و بارگذاری یک تصویر واضح، مسیر رشد مو را در سه بازه زمانی ببینید.',
                'cta_text' => 'شروع پیش نمایش',
                'consent_text' => 'با انتخاب تصویر، اجازه می‌دهم پیش‌نمایش اختصاصی برای شروع مشاوره بهتر آماده شود.',
                'footer_text' => 'یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.',
                'header_html' => '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6"><div><p class="text-sm font-bold text-emerald-700">کلینیک کاشت مو آریا</p><h1 class="mt-1 text-xl font-bold text-zinc-950">مشاوره و پیش‌نمایش اختصاصی کاشت مو</h1></div><nav class="flex items-center gap-4 text-sm font-bold text-zinc-600"><span>مشاوره</span><span>کاشت مو</span><span>پیگیری</span></nav></div>',
                'body_html' => null,
                'footer_html' => '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>کلینیک کاشت مو آریا - مشاوره، طراحی خط رویش و پیگیری درمان</p><p class="flex items-center gap-2"><img src="/theme/logo.svg" alt="" class="h-8"></p></div>',
                'back_link_title' => 'بازگشت به سایت کلینیک',
                'back_link_url' => 'https://hairvision-ai.test',
            ]
        );

        $this->syncTenantRole($tenant, $admin, 'clinic_admin');
        $this->syncTenantRole($tenant, $editor, 'editor');
        $this->syncTenantRole($tenant, $viewer, 'viewer');

        $this->seedActiveClinicPlan($tenant, $admin);

        $demoLeads = [
            [
                'full_name' => 'مراجع آزمایشی',
                'phone' => '09217359491',
                'source' => 'landing_page',
                'status' => 'verified',
                'crm_stage' => 'needs_follow_up',
                'assigned_user_id' => $admin->id,
                'created_at' => now()->subDays(1),
                'note' => 'این لید برای پیگیری مدیر کلینیک آماده شده است.',
            ],
            [
                'full_name' => 'نیما رضایی',
                'phone' => '09125550101',
                'source' => 'instagram',
                'status' => 'new',
                'crm_stage' => 'new_lead',
                'assigned_user_id' => $editor->id,
                'created_at' => now()->subHours(4),
                'note' => 'درخواست مشاوره اولیه از کمپین شبکه اجتماعی ثبت شد.',
            ],
            [
                'full_name' => 'سارا محمدی',
                'phone' => '09125550102',
                'source' => 'clinic_referral',
                'status' => 'contacted',
                'crm_stage' => 'consultation_booked',
                'assigned_user_id' => $admin->id,
                'created_at' => now()->subDays(3),
                'note' => 'زمان مشاوره حضوری برای هفته آینده هماهنگ شد.',
            ],
            [
                'full_name' => 'کامران احمدی',
                'phone' => '09125550103',
                'source' => 'google_search',
                'status' => 'qualified',
                'crm_stage' => 'converted',
                'assigned_user_id' => $editor->id,
                'created_at' => now()->subDays(8),
                'note' => 'پس از مشاهده نمونه نتایج، برای شروع فرآیند درمان اعلام آمادگی کرد.',
            ],
            [
                'full_name' => 'مهسا کریمی',
                'phone' => '09125550104',
                'source' => 'phone_call',
                'status' => 'archived',
                'crm_stage' => 'lost',
                'assigned_user_id' => null,
                'created_at' => now()->subDays(14),
                'note' => 'فعلا تمایلی به ادامه مشاوره ندارد و برای پیگیری آینده آرشیو شد.',
            ],
        ];

        foreach ($demoLeads as $leadData) {
            $lead = Lead::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'phone_normalized' => $leadData['phone']],
                [
                    'full_name' => $leadData['full_name'],
                    'phone' => $leadData['phone'],
                    'source' => $leadData['source'],
                    'phone_verified_at' => now(),
                    'status' => $leadData['status'],
                    'crm_stage' => $leadData['crm_stage'],
                    'crm_last_note_at' => $leadData['created_at']->copy()->addMinutes(30),
                    'assigned_user_id' => $leadData['assigned_user_id'],
                ]
            );

            $lead->forceFill([
                'created_at' => $leadData['created_at'],
                'updated_at' => $leadData['created_at']->copy()->addMinutes(30),
            ])->saveQuietly();

            $note = LeadNote::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'lead_id' => $lead->id,
                    'note' => $leadData['note'],
                ],
                [
                    'user_id' => $leadData['assigned_user_id'] ?? $admin->id,
                    'type' => 'admin_note',
                    'status' => $leadData['crm_stage'],
                    'communication_channel' => 'phone',
                ]
            );

            $note->forceFill([
                'created_at' => $leadData['created_at']->copy()->addMinutes(30),
                'updated_at' => $leadData['created_at']->copy()->addMinutes(30),
            ])->saveQuietly();
        }
    }

    private function seedAccessControl(): void
    {
        $catalog = app(PermissionCatalog::class);

        foreach ($catalog->permissions() as $group => $permissions) {
            foreach ($permissions as $permission) {
                Permission::query()->updateOrCreate(
                    ['code' => $permission['code']],
                    [
                        'name' => $permission['name'],
                        'group' => $group,
                        'description' => $permission['description'] ?? null,
                    ]
                );
            }
        }

        foreach ($catalog->roles() as $code => $roleDefinition) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $roleDefinition['name'],
                    'description' => $roleDefinition['description'],
                    'is_system' => true,
                    'status' => 'active',
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('code', $roleDefinition['permissions'], 'and', false)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
            app(PermissionService::class)->forgetRole($role->code);
        }
    }

    private function seedSystemDefaults(): void
    {
        foreach ($this->defaultSettings() as $key => $setting) {
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

        SmsGateway::query()->updateOrCreate(
            ['owner_scope' => 'system', 'provider_code' => 'local', 'user_id' => null],
            [
                'name' => __('messages.local_sms_gateway'),
                'status' => 'enabled',
                'is_default' => false,
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'system_messages'],
            ]
        );

        $ippanelToken = config('hairvision.sms.ippanel.authorization_token') ?: self::DEFAULT_IPPANEL_AUTHORIZATION_TOKEN;
        $ippanelFromNumber = config('hairvision.sms.ippanel.from_number');

        if (filled($ippanelToken)) {
            SmsGateway::query()->updateOrCreate(
                ['owner_scope' => 'system', 'provider_code' => 'ippanel', 'user_id' => null],
                [
                    'name' => __('messages.ippanel_sms_gateway'),
                    'status' => 'enabled',
                    'is_default' => true,
                    'encrypted_credentials' => ['api_key' => $ippanelToken],
                    'settings_json' => array_filter([
                        'purpose' => 'system_messages',
                        'from_number' => $ippanelFromNumber,
                        'sender' => $ippanelFromNumber,
                        'otp_pattern_code' => config('hairvision.sms.ippanel.otp_pattern_code'),
                        'otp_pattern_variable' => config('hairvision.sms.ippanel.otp_pattern_variable'),
                        'endpoint_url' => config('hairvision.sms.ippanel.endpoint_url'),
                    ]),
                ]
            );
        }

        PaymentGateway::query()->updateOrCreate(
            ['provider_code' => 'local'],
            [
                'name' => __('messages.local_payment_gateway'),
                'status' => 'enabled',
                'is_default' => false,
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'local_billing_tests'],
            ]
        );

        $zarinpalGateway = PaymentGateway::query()->updateOrCreate(
            ['provider_code' => 'zarinpal'],
            [
                'name' => __('messages.zarinpal_test_payment_gateway'),
                'status' => 'enabled',
                'is_default' => true,
                'encrypted_credentials' => [
                    'merchant_id' => self::DEFAULT_ZARINPAL_SANDBOX_MERCHANT_ID,
                ],
                'settings_json' => [
                    'purpose' => 'zarinpal_sandbox_billing_tests',
                    'mode' => 'sandbox',
                    'sandbox_base_url' => 'https://sandbox.zarinpal.com',
                    'request_url' => 'https://sandbox.zarinpal.com/pg/v4/payment/request.json',
                    'verify_url' => 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json',
                    'start_pay_url' => 'https://sandbox.zarinpal.com/pg/StartPay/',
                ],
            ]
        );

        app(PaymentGatewayService::class)->markDefault($zarinpalGateway);

        $starterPlanCredits = $this->starterPlanServiceCredits();

        $starterPlan = Plan::query()->updateOrCreate(
            ['name' => 'پلن شروع'],
            [
                'description' => 'پلن فعال کلینیک آریا برای استفاده کامل از امکانات MVP.',
                'plan_type' => 'base_subscription',
                'service_key' => count($starterPlanCredits) === 1 ? array_key_first($starterPlanCredits) : null,
                'price' => 9900000,
                'currency' => 'IRR',
                'duration_days' => 30,
                'usage_limit' => array_sum($starterPlanCredits),
                'features_json' => $this->starterPlanFeatures(),
                'status' => 'active',
            ]
        );

        foreach ($starterPlanCredits as $serviceKey => $credit) {
            $starterPlan->serviceEntitlements()->updateOrCreate(
                ['service_key' => $serviceKey],
                [
                    'credit_amount' => $credit,
                    'is_active' => true,
                ]
            );
        }

        $starterPlan->serviceEntitlements()
            ->whereNotIn('service_key', array_keys($starterPlanCredits))
            ->update(['is_active' => false]);
    }

    private function seedActiveClinicPlan(Tenant $tenant, User $admin): void
    {
        $starterPlan = Plan::query()
            ->with('serviceEntitlements')
            ->where('name', 'پلن شروع')
            ->firstOrFail();

        $activeStarter = $this->activeStarterSubscription($tenant, $starterPlan);
        $now = now();

        $invoice = Invoice::query()->firstOrNew([
            'number' => 'HV-SEED-aria-STARTER',
        ]);

        $invoice->fill([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'plan_id' => $starterPlan->id,
            'status' => 'paid',
            'amount' => $starterPlan->price,
            'currency' => $starterPlan->currency,
            'due_at' => $now,
            'paid_at' => $now,
            'expires_at' => $now->copy()->addDays((int) $starterPlan->duration_days),
            'metadata_json' => [
                'seeded' => true,
                'clinic' => 'aria',
                'plan_type' => $starterPlan->plan_type->value,
                'service_credits' => $starterPlan->serviceCredits(),
            ],
        ]);

        if (! $activeStarter) {
            $invoice->activated_at = null;
        } elseif (! $invoice->activated_at) {
            $invoice->activated_at = $activeStarter->starts_at;
        }

        $invoice->save();

        $gateway = PaymentGateway::query()->where('provider_code', 'local')->first()
            ?? PaymentGateway::query()->where('is_default', true)->first();

        if ($gateway) {
            Payment::query()->updateOrCreate(
                ['idempotency_key' => 'seed-aria-starter-plan-paid'],
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $admin->id,
                    'invoice_id' => $invoice->id,
                    'payment_gateway_id' => $gateway->id,
                    'status' => 'paid',
                    'amount' => $starterPlan->price,
                    'currency' => $starterPlan->currency,
                    'authority' => 'SEED-aria-STARTER',
                    'reference_id' => 'SEED-aria-PAID',
                    'gateway_response_json' => [
                        'provider' => $gateway->provider_code,
                        'seeded' => true,
                    ],
                    'verified_at' => $now,
                ]
            );
        }

        $subscription = $activeStarter;

        if (! $subscription) {
            $subscription = app(SubscriptionActivationService::class)
                ->activateInvoice($invoice->refresh());
        }

        if (! $subscription) {
            return;
        }

        $subscription->forceFill([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'plan_id' => $starterPlan->id,
            'invoice_id' => $subscription->invoice_id ?? $invoice->id,
            'status' => 'active',
            'usage_limit' => array_sum($starterPlan->serviceCredits()),
        ])->save();

        $this->syncSeededSubscriptionEntitlements($tenant, $admin, $starterPlan, $subscription->refresh(), $invoice->refresh());
    }

    private function activeStarterSubscription(Tenant $tenant, Plan $starterPlan): ?Subscription
    {
        return Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('plan_id', $starterPlan->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    private function syncSeededSubscriptionEntitlements(
        Tenant $tenant,
        User $admin,
        Plan $starterPlan,
        Subscription $subscription,
        Invoice $invoice,
    ): void {
        foreach ($starterPlan->fresh('serviceEntitlements')->serviceCredits() as $serviceKey => $credit) {
            $entitlement = SubscriptionServiceEntitlement::query()->updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'service_key' => $serviceKey,
                    'source_type' => 'base_plan',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $starterPlan->id,
                    'source_id' => $starterPlan->id,
                    'included_credit' => $credit,
                    'purchased_credit' => 0,
                    'is_active' => true,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                ]
            );

            CreditTransaction::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'subscription_service_entitlement_id' => $entitlement->id,
                    'service_key' => $serviceKey,
                    'type' => 'grant',
                    'related_type' => $invoice->getMorphClass(),
                    'related_id' => $invoice->id,
                ],
                [
                    'amount' => $credit,
                    'balance_after' => $entitlement->remainingCredit(),
                    'description' => __('credits.descriptions.base_grant', ['plan' => $starterPlan->name]),
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function starterPlanServiceCredits(): array
    {
        $serviceKeys = array_keys(config('hairvision.ai_services', [])) ?: ['hair_transplant'];

        return collect($serviceKeys)
            ->mapWithKeys(fn (string $serviceKey): array => [$serviceKey => 100])
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function starterPlanFeatures(): array
    {
        return [
            'ai_generation' => true,
            'ai_models' => true,
            'billing' => true,
            'crm' => true,
            'leads' => true,
            'reports' => true,
            'settings' => true,
            'sms' => true,
            'team' => true,
            'uploads' => true,
        ];
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
                'value' => 'HairVision AI',
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
     * @return array<string, string>
     */
    private function gapGptStagePrompts(): array
    {
        return [
            'two_weeks' => <<<'PROMPT'
Use the uploaded photo as the direct reference and generate exactly one ultra-realistic photo edit of the same person, showing the person two weeks after a complete and successful hair transplant.

Overall objective:
Create a realistic 2-week post-hair-transplant photo edit based on the uploaded image. The transplant must not be limited to the person’s existing hair. Any bald or thinning areas, especially the frontal hairline, temples, front scalp, mid-scalp, and top of the head, must be treated as transplant recipient areas where new hair has been implanted.

Very important transplant coverage rule:
If the original person has a completely bald or heavily receded front area, reconstruct the recipient area as if it has been fully implanted. Do not only thicken old remaining hair. Create a natural new hairline design and visible implantation pattern across the bald frontal and top scalp areas.

Stage requirements:

Show the same person 2 weeks after a complete hair transplant.
The transplanted zones should clearly show many visible transplant marks across the future hairline, frontal scalp, temples, mid-scalp, and top area where needed.
Include realistic small red dots, healing micro-scabs, and mild post-transplant redness across the implanted areas.
Hair should still appear very short, early-stage, and not grown in yet.
The pattern of transplant marks must show that the previously bald or thinning areas have been fully implanted.
Keep the result realistic, medically believable, and not overly graphic.

Critical identity and consistency requirements:

Preserve the exact same person.
Do not change the face, facial structure, skin tone, ethnicity, age, expression, camera angle, pose, clothing, background, lighting, or overall composition.
Keep the same framing and perspective as the original uploaded image.
Do not make the person look like someone else.
Do not change beard, eyebrows, eyes, nose, lips, jawline, ears, or facial details.
Do not change the clothes, clothing color, or clothing style.
Do not change the background in any way.
Do not add or remove objects.
The only allowed changes are the scalp, hairline, hair density, implanted area, and visible hair-transplant healing effects appropriate to this timeline.

General realism requirements:

Ultra-realistic medical-style photo edit.
The result must look like a real hair transplant progress photo, not a 3D render, painting, cartoon, or beauty-retouched image.
Keep natural skin texture, realistic scalp detail, and natural hair texture.
Avoid artificial perfection or over-editing.
Hair color and hair texture should remain consistent with the person’s natural appearance.

Output requirements:

Produce exactly one high-resolution realistic photo edit.
The output must look like the same person in the same original photo setup.
Do not alter any non-hair element of the original image.
PROMPT,
            'intermediate_regrowth' => <<<'PROMPT'
Use the uploaded photo as the direct reference and generate exactly one ultra-realistic photo edit of the same person, showing an intermediate hair regrowth stage after a complete and successful hair transplant.

Overall objective:
Create a realistic intermediate post-hair-transplant regrowth photo edit based on the uploaded image. The transplant must not be limited to the person’s existing hair. Any bald or thinning areas, especially the frontal hairline, temples, front scalp, mid-scalp, and top of the head, must show early new hair growth from the transplanted areas.

Very important transplant coverage rule:
If the original person has a completely bald or heavily receded front area, reconstruct it as a fully treated recipient area with new hair growth beginning across the bald frontal and top scalp areas. Do not only thicken old remaining hair. Create a natural new hairline with sparse and uneven early regrowth.

Stage requirements:

Show the same person at a later intermediate stage after the transplant.
The red transplant wounds and scabs should be mostly healed or no longer visible.
New hair should appear across the transplanted bald or thinning areas, including the front hairline, temples, mid-scalp, and top scalp.
Hair should still look thin, sparse, uneven, and incomplete.
The scalp should still be partially visible, showing that the final density has not fully developed yet.
This stage should look clearly improved compared to the 2-week stage, but not yet full or final.
The new hairline should be visible but still developing.

Critical identity and consistency requirements:

Preserve the exact same person.
Do not change the face, facial structure, skin tone, ethnicity, age, expression, camera angle, pose, clothing, background, lighting, or overall composition.
Keep the same framing and perspective as the original uploaded image.
Do not make the person look like someone else.
Do not change beard, eyebrows, eyes, nose, lips, jawline, ears, or facial details.
Do not change the clothes, clothing color, or clothing style.
Do not change the background in any way.
Do not add or remove objects.
The only allowed changes are the scalp, hairline, hair density, implanted area, and hair regrowth stage.

General realism requirements:

Ultra-realistic medical-style photo edit.
The result must look like a real hair transplant progress photo, not a 3D render, painting, cartoon, or beauty-retouched image.
Keep natural skin texture, realistic scalp detail, and natural hair texture.
Avoid artificial perfection, fake density, or unrealistic hairlines.
Hair color and hair texture should remain consistent with the person’s natural appearance.

Output requirements:

Produce exactly one high-resolution realistic photo edit.
The output must look like the same person in the same original photo setup.
Do not alter any non-hair element of the original image.
PROMPT,
            'final_result' => <<<'PROMPT'
Use the uploaded photo as the direct reference and generate exactly one ultra-realistic photo edit of the same person, showing the final successful result after full hair growth from a complete hair transplant.

Overall objective:
Create a realistic final 1-year post-hair-transplant result based on the uploaded image. The transplant must not be limited to the person’s existing hair. Any bald or thinning areas, especially the frontal hairline, temples, front scalp, mid-scalp, and top of the head, must now look naturally filled in with fully grown transplanted hair.

Very important transplant coverage rule:
If the original person has a completely bald or heavily receded front area, reconstruct it as a successful complete transplant result. Do not only thicken old remaining hair. Create a realistic full new hairline and new hair growth across the bald frontal and top scalp areas, as if the person no longer appears bald in the transplanted areas.

Stage requirements:

Show the same person at the final successful result stage after full hair growth.
The previously bald or thinning front and top areas should now look naturally filled in.
Create a realistic full hairline where needed, including the frontal area and temples.
The person should no longer look bald in the transplanted areas.
Hair should appear fully grown, naturally fuller, and complete across the front, mid-scalp, and top.
The hairline must be natural, age-appropriate, and proportional to the face.
The density should look successful and full, but still realistic and slightly imperfect.
Avoid an unnaturally perfect, overly dense, fake, or wig-like result.
The final image should look like a real successful 1-year post-transplant result, as if the person naturally has a full head of hair again.

Critical identity and consistency requirements:

Preserve the exact same person.
Do not change the face, facial structure, skin tone, ethnicity, age, expression, camera angle, pose, clothing, background, lighting, or overall composition.
Keep the same framing and perspective as the original uploaded image.
Do not make the person look like someone else.
Do not change beard, eyebrows, eyes, nose, lips, jawline, ears, or facial details.
Do not change the clothes, clothing color, or clothing style.
Do not change the background in any way.
Do not add or remove objects.
The only allowed changes are the scalp, hairline, hair density, and fully grown transplanted hair.

General realism requirements:

Ultra-realistic medical-style photo edit.
The result must look like a real successful hair transplant photo, not a 3D render, painting, cartoon, or beauty-retouched image.
Keep natural skin texture, realistic scalp detail, and natural hair texture.
Avoid artificial perfection, fake density, or unrealistic hairlines.
Hair color and hair texture should remain consistent with the person’s natural appearance.
The final hair should look full and successful, but not like a wig or artificial hairpiece.

Output requirements:

Produce exactly one high-resolution realistic photo edit.
The output must look like the same person in the same original photo setup.
Do not alter any non-hair element of the original image.
PROMPT,
        ];
    }

    private function upsertUser(string $name, string $email, string $phone, string $role): User
    {
        $user = User::query()
            ->where('phone_normalized', $phone)
            ->orWhere('email', $email)
            ->when($role === 'super_admin', fn ($query) => $query->orWhere('role', 'super_admin'))
            ->first();

        if (! $user) {
            $user = new User([
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'phone_normalized' => $phone,
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $user;
    }

    private function syncTenantRole(Tenant $tenant, User $user, string $role): void
    {
        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'status' => 'active',
            ],
        ]);

        $tenant->users()->updateExistingPivot($user->id, [
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
