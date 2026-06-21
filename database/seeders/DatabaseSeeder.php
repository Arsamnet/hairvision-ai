<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Allow model events so model boot methods (like UUID generation) run during seeding.

    public function run(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'مدیر سیستم HairVision',
            'email' => 'superadmin@example.com',
            'phone_normalized' => '09129999999',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'name' => 'مدیر کلینیک آریا',
            'email' => 'test@example.com',
            'phone_normalized' => '09120000000',
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        $editor = User::factory()->create([
            'name' => 'کارشناس پذیرش آریا',
            'email' => 'editor@example.com',
            'phone_normalized' => '09120000001',
            'role' => 'editor',
            'status' => 'active',
        ]);

        $viewer = User::factory()->create([
            'name' => 'گزارش‌گیر آریا',
            'email' => 'viewer@example.com',
            'phone_normalized' => '09120000002',
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $tenant = Tenant::create([
            'name' => 'کلینیک آریا',
            'slug' => 'clinic1',
            'status' => 'active',
        ]);

        // Visitor-facing tenant domain
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'clinic1.hairvision-ai.test',
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        // Platform admin domain
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'hairvision-ai.test',
            'type' => 'platform',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        $provider = AiProvider::create([
            'code' => 'sample_image',
            'name' => 'Sample Image Provider',
            'type' => 'local',
            'is_active' => true,
            'supports_async' => false,
        ]);

        TenantAiSetting::create([
            'tenant_id' => $tenant->id,
            'provider_id' => $provider->id,
            'is_default' => true,
        ]);

        $template = Template::create([
            'code' => 'classic',
            'name' => 'Classic',
            'is_active' => true,
        ]);

        TenantThemeSetting::create([
            'tenant_id' => $tenant->id,
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
        ]);

        $tenant->users()->attach($admin->id, [
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        $tenant->users()->attach($editor->id, [
            'role' => 'editor',
            'status' => 'active',
        ]);

        $tenant->users()->attach($viewer->id, [
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $demoLeads = [
            [
                'full_name' => 'مراجع آزمایشی',
                'phone' => '09120000000',
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
            $lead = Lead::create([
                'tenant_id' => $tenant->id,
                'full_name' => $leadData['full_name'],
                'phone' => $leadData['phone'],
                'phone_normalized' => $leadData['phone'],
                'source' => $leadData['source'],
                'phone_verified_at' => now(),
                'status' => $leadData['status'],
                'crm_stage' => $leadData['crm_stage'],
                'crm_last_note_at' => $leadData['created_at']->copy()->addMinutes(30),
                'assigned_user_id' => $leadData['assigned_user_id'],
            ]);

            $lead->forceFill([
                'created_at' => $leadData['created_at'],
                'updated_at' => $leadData['created_at']->copy()->addMinutes(30),
            ])->saveQuietly();

            $note = LeadNote::create([
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'user_id' => $leadData['assigned_user_id'] ?? $admin->id,
                'type' => 'admin_note',
                'note' => $leadData['note'],
            ]);

            $note->forceFill([
                'created_at' => $leadData['created_at']->copy()->addMinutes(30),
                'updated_at' => $leadData['created_at']->copy()->addMinutes(30),
            ])->saveQuietly();
        }
    }
}
