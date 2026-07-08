<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantThemeSetting;
use Illuminate\Database\Seeder;

class TenantThemeSettingSeeder extends Seeder
{
    public function run(): void
    {
        $template = Template::query()->where('code', 'classic')->firstOrFail();

        foreach ($this->themes() as $slug => $theme) {
            $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

            TenantThemeSetting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                array_merge($theme, ['template_id' => $template->id])
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function themes(): array
    {
        return [
            'aria' => [
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
                'back_link_url' => 'https://hairvision.ir',
            ],
            'noja' => [
                'favicon_path' => null,
                'primary_color' => '#7c3aed',
                'hero_title' => 'پیش نمایش هوشمند مسیر کاشت مو',
                'hero_subtitle' => 'با تایید شماره و بارگذاری یک تصویر واضح، مسیر رشد مو را در سه بازه زمانی ببینید.',
                'cta_text' => 'شروع پیش نمایش',
                'consent_text' => 'با انتخاب تصویر، اجازه می‌دهم پیش‌نمایش اختصاصی برای شروع مشاوره بهتر آماده شود.',
                'footer_text' => 'یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک نوژا.',
                'header_html' => '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6"><div><p class="text-sm font-bold text-violet-700">کلینیک نوژا</p><h1 class="mt-1 text-xl font-bold text-zinc-950">مشاوره و پیش‌نمایش اختصاصی کاشت مو</h1></div><nav class="flex items-center gap-4 text-sm font-bold text-zinc-600"><span>مشاوره</span><span>کاشت مو</span><span>پیگیری</span></nav></div>',
                'body_html' => null,
                'footer_html' => '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>کلینیک نوژا - مشاوره، طراحی خط رویش و پیگیری درمان</p><p class="flex items-center gap-2"><img src="/theme/logo.svg" alt="" class="h-8"></p></div>',
                'back_link_title' => 'بازگشت به سایت کلینیک',
                'back_link_url' => 'https://hairvision.ir',
            ],
        ];
    }
}
