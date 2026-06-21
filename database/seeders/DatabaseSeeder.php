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
        $admin = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
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

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09120000000',
            'phone_normalized' => '09120000000',
            'phone_verified_at' => now(),
            'status' => 'verified',
            'crm_stage' => 'needs_follow_up',
            'crm_last_note_at' => now(),
        ]);

        LeadNote::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'user_id' => $admin->id,
            'type' => 'admin_note',
            'note' => 'این لید برای پیگیری ادمین کلینیک آماده شده است. پس از آماده شدن داشبورد، وضعیت و توضیحات از همین ساختار مدیریت می شوند.',
        ]);
    }
}
