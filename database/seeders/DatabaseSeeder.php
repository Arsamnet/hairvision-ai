<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\AiModelAccess;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\PaymentGateway;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\User;
use App\Services\Auth\PermissionCatalog;
use App\Services\Auth\PermissionService;
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
            ['slug' => 'clinic1'],
            [
                'name' => 'کلینیک آریا',
                'status' => 'active',
            ]
        );

        // Visitor-facing tenant domain
        TenantDomain::query()->updateOrCreate(
            ['domain' => 'clinic1.hairvision-ai.test'],
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
                'is_active' => true,
                'supports_async' => false,
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

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $demoAccess->id => [
                'is_active' => true,
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
                ->whereIn('code', $roleDefinition['permissions'])
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
                'is_default' => true,
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'system_messages'],
            ]
        );

        $ippanelToken = config('hairvision.sms.ippanel.authorization_token')
            ?: self::DEFAULT_IPPANEL_AUTHORIZATION_TOKEN;
        $ippanelFromNumber = config('hairvision.sms.ippanel.from_number');

        SmsGateway::query()->updateOrCreate(
            ['owner_scope' => 'system', 'provider_code' => 'ippanel', 'user_id' => null],
            [
                'name' => __('messages.ippanel_sms_gateway'),
                'status' => 'enabled',
                'is_default' => false,
                'encrypted_credentials' => filled($ippanelToken) ? ['api_key' => $ippanelToken] : [],
                'settings_json' => array_filter([
                    'purpose' => 'system_messages',
                    'from_number' => $ippanelFromNumber,
                    'sender' => $ippanelFromNumber,
                    'otp_pattern_code' => config('hairvision.sms.ippanel.otp_pattern_code'),
                    'endpoint_url' => config('hairvision.sms.ippanel.endpoint_url'),
                ]),
            ]
        );

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

        $starterPlan = Plan::query()->updateOrCreate(
            ['name' => 'پلن شروع'],
            [
                'description' => 'پلن نمونه برای تست خرید، تمدید و ارتقا در MVP.',
                'plan_type' => 'base_subscription',
                'service_key' => 'hair_transplant',
                'price' => 9900000,
                'currency' => 'IRR',
                'duration_days' => 30,
                'usage_limit' => 100,
                'features_json' => ['leads' => true, 'reports' => true],
                'status' => 'active',
            ]
        );

        $starterPlan->serviceEntitlements()->updateOrCreate(
            ['service_key' => 'hair_transplant'],
            [
                'credit_amount' => 100,
                'is_active' => true,
            ]
        );
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
