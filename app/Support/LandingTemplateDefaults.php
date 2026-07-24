<?php

namespace App\Support;

/**
 * Provides the built-in landing template fallback without depending on seeded tenant data.
 *
 * @since 2.0.0
 */
final class LandingTemplateDefaults
{
    public const CODE = 'classic';

    public const PRIMARY_COLOR = '#0f766e';

    /**
     * Return the editable content used when the classic template is first created.
     *
     * @return array{
     *     primary_color: string,
     *     favicon_path: null,
     *     consent_text: string,
     *     header_html: string,
     *     body_html: null,
     *     footer_html: string,
     *     back_link_title: null,
     *     back_link_url: null
     * }
     *
     * @since 2.0.0
     */
    public static function content(): array
    {
        return [
            'primary_color' => self::PRIMARY_COLOR,
            'favicon_path' => null,
            'consent_text' => 'با پردازش تصویر برای این سرویس موافقم.',
            'header_html' => <<<'HTML'
<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
    <div>
        <p class="text-sm font-bold text-emerald-700">کلینیک کاشت مو</p>
        <h1 class="mt-1 text-xl font-bold text-zinc-950">مشاوره و پیش نمایش اختصاصی کاشت مو</h1>
    </div>
</div>
HTML,
            'body_html' => null,
            'footer_html' => <<<'HTML'
<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6">
    <p>یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.</p>
    <p class="flex items-center gap-2">
        <span>قدرت گرفته از</span>
        <span class="grid h-7 w-7 place-items-center rounded-xl bg-linear-to-br from-emerald-500 via-indigo-500 to-fuchsia-500">
            <img src="/theme/admin-logo-dark.svg" alt="" class="h-5 w-5">
        </span>
    </p>
</div>
HTML,
            'back_link_title' => null,
            'back_link_url' => null,
        ];
    }
}
