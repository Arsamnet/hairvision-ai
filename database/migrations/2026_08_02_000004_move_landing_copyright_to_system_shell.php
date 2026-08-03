<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes built-in HairVision branding from editable landing footer fragments.
 *
 * Only exact, unchanged built-in values are migrated so administrator-authored
 * footer customizations remain untouched.
 *
 * @since 2.1.0
 */
return new class extends Migration
{
    private const CLASSIC_FOOTER_BEFORE = <<<'HTML'
<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6">
    <p>یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.</p>
    <p class="flex items-center gap-2">
        <span class="pt-1">قدرت گرفته از</span>
        <span class="grid h-7 w-7 place-items-center rounded-xl bg-linear-to-br from-emerald-500 via-indigo-500 to-fuchsia-500">
            <img src="/theme/admin-logo-dark.svg" alt="" class="h-5 w-5">
        </span>
    </p>
</div>
HTML;

    private const CLASSIC_FOOTER_AFTER = <<<'HTML'
<div class="mx-auto flex w-full max-w-6xl items-center px-4 py-5 text-sm text-zinc-500 sm:px-6">
    <p>یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.</p>
</div>
HTML;

    /** @var array<string, string> Exact seeded legacy footer replacements. */
    private const TENANT_FOOTER_REPLACEMENTS = [
        '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>کلینیک کاشت مو آریا - مشاوره، طراحی خط رویش و پیگیری درمان</p><p class="flex items-center gap-2"><img src="/theme/logo.svg" alt="" class="h-8"></p></div>' => '<div class="mx-auto flex w-full max-w-6xl items-center px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>کلینیک کاشت مو آریا - مشاوره، طراحی خط رویش و پیگیری درمان</p></div>',
        '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>کلینیک نوژا - مشاوره، طراحی خط رویش و پیگیری درمان</p><p class="flex items-center gap-2"><img src="/theme/logo.svg" alt="" class="h-8"></p></div>' => '<div class="mx-auto flex w-full max-w-6xl items-center px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>کلینیک نوژا - مشاوره، طراحی خط رویش و پیگیری درمان</p></div>',
    ];

    /**
     * Move unchanged built-in footer branding into the code-owned landing shell.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function up(): void
    {
        if (Schema::hasTable('templates') && Schema::hasColumn('templates', 'footer_html')) {
            DB::table('templates')
                ->where('code', 'classic')
                ->where('footer_html', self::CLASSIC_FOOTER_BEFORE)
                ->update(['footer_html' => self::CLASSIC_FOOTER_AFTER]);
        }

        if (! Schema::hasTable('tenant_theme_settings') || ! Schema::hasColumn('tenant_theme_settings', 'footer_html')) {
            return;
        }

        foreach (self::TENANT_FOOTER_REPLACEMENTS as $before => $after) {
            DB::table('tenant_theme_settings')
                ->where('footer_html', $before)
                ->update(['footer_html' => $after]);
        }
    }

    /**
     * Restore unchanged built-in footer branding when rolling back this change.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function down(): void
    {
        if (Schema::hasTable('templates') && Schema::hasColumn('templates', 'footer_html')) {
            DB::table('templates')
                ->where('code', 'classic')
                ->where('footer_html', self::CLASSIC_FOOTER_AFTER)
                ->update(['footer_html' => self::CLASSIC_FOOTER_BEFORE]);
        }

        if (! Schema::hasTable('tenant_theme_settings') || ! Schema::hasColumn('tenant_theme_settings', 'footer_html')) {
            return;
        }

        foreach (self::TENANT_FOOTER_REPLACEMENTS as $before => $after) {
            DB::table('tenant_theme_settings')
                ->where('footer_html', $after)
                ->update(['footer_html' => $before]);
        }
    }
};
