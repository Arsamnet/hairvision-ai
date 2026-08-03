{{-- @since 2.1.0 Rebuilds the tenant landing shell, upload journey, SEO, and analytics contract. --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <?php
        /** @var \App\Models\Tenant $tenant */
        /** @var array<string, mixed> $landingTheme */
        /** @var string $landingAnalyticsToken */
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
        $defaultFaviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png');
        $faviconHref = app(\App\Services\Landing\LandingTemplateResolver::class)->faviconHref($landingTheme, $defaultFaviconHref);
        $primaryColor = (string) ($landingTheme['primary_color'] ?? '#0f766e');
        $primaryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) ? $primaryColor : '#0f766e';
        $hairConsultationHref = '/theme/category-images/hair-transplant.jpg';
        $faceConsultationHref = '/theme/category-images/eyebrow-transplant.jpg';
        $seoTitle = trim((string) ($landingTheme['seo_title'] ?? '')) ?: (($tenant->name ?? 'کلینیک').' | پیش‌نمایش هوشمند HairVision');
        $seoDescription = trim((string) ($landingTheme['seo_description'] ?? '')) ?: ('پیش‌نمایش هوشمند خدمات زیبایی برای شروع مشاوره دقیق‌تر با '.($tenant->name ?? 'کلینیک').'.');
        $canonicalUrl = rtrim(request()->getSchemeAndHttpHost(), '/').'/';
        $seoImagePath = \App\Support\LandingAssetUrl::resolve($landingTheme['seo_image_path'] ?? null, '');
        $seoImageUrl = $seoImagePath === ''
            ? null
            : (str_starts_with($seoImagePath, '/') ? rtrim(request()->getSchemeAndHttpHost(), '/').$seoImagePath : $seoImagePath);
        $seoRobots = (string) ($landingTheme['seo_robots'] ?? 'index,follow');
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'MedicalClinic',
            'name' => (string) ($tenant->name ?? 'کلینیک'),
            'url' => $canonicalUrl,
            'description' => $seoDescription,
        ];

        if ($seoImageUrl) {
            $structuredData['image'] = $seoImageUrl;
        }
    ?>
    <title><?php echo e($seoTitle); ?></title>
    <meta name="description" content="<?php echo e($seoDescription); ?>">
    <meta name="robots" content="<?php echo e($seoRobots); ?>">
    <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo e($tenant->name ?? 'HairVision'); ?>">
    <meta property="og:title" content="<?php echo e($seoTitle); ?>">
    <meta property="og:description" content="<?php echo e($seoDescription); ?>">
    <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
    <meta name="twitter:card" content="<?php echo $seoImageUrl ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo e($seoTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($seoDescription); ?>">
    <?php if ($seoImageUrl): ?>
        <meta property="og:image" content="<?php echo e($seoImageUrl); ?>">
        <meta name="twitter:image" content="<?php echo e($seoImageUrl); ?>">
    <?php endif; ?>
    <script type="application/ld+json"><?php echo json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    <?php echo $landingTheme['tracking_head_code'] ?? ''; ?>

    <?php echo app('Illuminate\Foundation\Vite')([
        'resources/css/app.css',
        'resources/css/landing-icons.css',
        'resources/js/app.js',
    ]); ?>
    <style>
        @keyframes hv-enter {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes hv-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes hv-ai-border-flow {
            0% { background-position: 0 0, 0% 50%; }
            50% { background-position: 0 0, 100% 50%; }
            100% { background-position: 0 0, 0% 50%; }
        }

        @keyframes hv-soft-pulse {
            0%, 100% { opacity: .55; transform: scale(.98); }
            50% { opacity: 1; transform: scale(1); }
        }

        @keyframes hv-skeleton {
            0% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .hv-enter { animation: hv-enter .25s ease-out both; }
        .hv-ai-cast {
            background: linear-gradient(120deg, rgba(20,184,166,.22), rgba(99,102,241,.24), rgba(168,85,247,.2), rgba(14,165,233,.2), rgba(45,212,191,.2));
            background-size: 320% 320%;
            animation: hv-shift 2.6s ease-in-out infinite;
        }
        .hv-live-dot { animation: hv-soft-pulse 1.1s ease-in-out infinite; }
        .hv-journey-progress {
            border: 1px solid rgba(255,255,255,.82);
            background: rgba(255,255,255,.72);
            box-shadow: 0 12px 34px rgba(79,70,229,.08);
        }
        .hv-stepper {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .45rem;
        }
        .hv-step {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            display: flex;
            min-width: 0;
            min-height: 4rem;
            align-items: center;
            gap: .55rem;
            border-radius: 1rem;
            padding: .6rem;
            text-align: start;
        }
        .hv-step::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: 0;
            transition: opacity .25s ease;
            background: linear-gradient(135deg, rgba(20,184,166,.18), rgba(99,102,241,.18), rgba(168,85,247,.12));
        }
        .hv-step-active {
            color: #fff;
            background: linear-gradient(135deg, #0f172a, #312e81 52%, #0f766e);
            box-shadow: 0 12px 30px rgba(49,46,129,.2);
        }
        .hv-step-done {
            color: #065f46;
            background: linear-gradient(135deg, #d1fae5, #ecfeff);
        }
        .hv-step-upcoming {
            color: #52525b;
            background: rgba(244,244,245,.78);
        }
        .hv-step-marker {
            display: grid;
            width: 1.85rem;
            height: 1.85rem;
            flex: 0 0 auto;
            place-items: center;
            border-radius: .75rem;
            font-size: 1rem;
            font-weight: 700;
        }
        .hv-step-copy {
            min-width: 0;
        }
        .hv-step-title {
            display: block;
            overflow: hidden;
            font-size: .75rem;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .hv-step-caption {
            display: none;
            margin-top: .12rem;
            overflow: hidden;
            font-size: .67rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .hv-button {
            min-height: 3rem;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            border: 1px solid transparent;
            border-radius: 1rem;
            padding: .7rem 1.25rem;
            font-size: .875rem;
            font-weight: 700;
            line-height: 1.35;
            text-align: center;
            text-decoration: none;
            appearance: none;
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            transition:
                color .18s ease,
                border-color .18s ease,
                background-color .18s ease,
                box-shadow .18s ease,
                filter .18s ease;
        }
        .hv-button-compact {
            min-height: 2.75rem;
            border-radius: 1rem;
            padding: .55rem .9rem;
            font-size: .75rem;
        }
        .hv-button-icon {
            width: 2.75rem;
            min-width: 2.75rem;
            min-height: 2.75rem;
            border-radius: 9999px;
            padding: 0;
        }
        .hv-button-primary,
        .hv-primary-action {
            border: 2px solid transparent;
            color: #fff;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--hv-primary-color, #0f766e) 24%, #4f46e5), #4338ca 52%, #0f766e) padding-box,
                linear-gradient(110deg, #5eead4, #22d3ee, #818cf8, #c084fc, #5eead4) border-box;
            background-size: 100% 100%, 260% 100%;
            animation: hv-ai-border-flow 6s linear infinite;
            box-shadow: 0 5px 16px rgba(67,56,202,.16), inset 0 1px 0 rgba(255,255,255,.2);
        }
        .hv-button-secondary,
        .hv-secondary-action,
        .hv-lift {
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 24%, #d4d4d8);
            color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 72%, #18181b);
            background: rgba(255,255,255,.94);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.86);
        }
        .hv-button-on-dark {
            border-color: rgba(255,255,255,.7);
            color: #18181b;
            background: rgba(255,255,255,.94);
            box-shadow: 0 4px 14px rgba(0,0,0,.14);
            backdrop-filter: blur(10px);
        }
        .hv-button:active:not(:disabled),
        .hv-primary-action:active:not(:disabled),
        .hv-secondary-action:active:not(:disabled),
        .hv-lift:active:not(:disabled) {
            filter: brightness(.97) saturate(.96);
        }
        .hv-button:disabled,
        .hv-primary-action:disabled,
        .hv-secondary-action:disabled {
            border-color: #d4d4d8;
            color: #71717a;
            background: #e4e4e7;
            animation: none;
            box-shadow: none;
            filter: none;
        }
        .hv-upload-dropzone::after {
            content: '';
            position: absolute;
            inset: 10px;
            z-index: 0;
            border-radius: 1.4rem;
            opacity: 0;
            transition: opacity .25s ease;
            background: linear-gradient(135deg, rgba(20,184,166,.1), rgba(99,102,241,.12), rgba(168,85,247,.08));
            pointer-events: none;
        }
        .hv-upload-dropzone:hover::after,
        .hv-upload-dropzone.is-dragging::after { opacity: 1; }
        .hv-upload-dropzone.is-dragging {
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 65%, #6366f1);
            background: #eef2ff;
            box-shadow: 0 0 0 4px rgba(99,102,241,.12);
        }
        .hv-upload-dropzone:has(#image:focus-visible) {
            outline: 3px solid color-mix(in srgb, var(--hv-primary-color, #0f766e) 58%, #6366f1);
            outline-offset: 3px;
        }
        .hv-ai-input {
            background:
                linear-gradient(#fff, #fff) padding-box,
                linear-gradient(120deg, var(--hv-primary-color, #14b8a6), #22d3ee, #6366f1, #a855f7, var(--hv-primary-color, #14b8a6)) border-box;
            background-size: 100% 100%, 260% 260%;
            border: 1px solid transparent;
            animation: hv-shift 4.5s ease-in-out infinite;
            box-shadow: 0 10px 30px rgba(79,70,229,.08);
        }
        .hv-ai-input:focus {
            box-shadow: 0 0 0 4px rgba(45,212,191,.16), 0 16px 36px rgba(79,70,229,.12);
        }
        .hv-logo-slot {
            background: rgba(255,255,255,.1);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.16), 0 12px 30px rgba(49,46,129,.24);
        }
        .hv-logo-slot img {
            max-height: 2.75rem;
            max-width: 9rem;
        }
        .hv-service-visual {
            object-fit: cover;
            transition: filter .18s ease;
        }
        .hv-treatment-card.is-selected .hv-service-visual {
            filter: saturate(1.08) contrast(1.04);
        }
        .hv-result-card img {
            transition: filter .18s ease;
        }
        .hv-modal-image {
            transition: opacity .22s ease, transform .22s ease;
        }
        .hv-modal-image.is-changing {
            opacity: 0;
            transform: scale(.985) translateY(6px);
        }
        .hv-generation-stage {
            border: 1px solid rgba(228,228,231,.95);
            background: rgba(255,255,255,.82);
            transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
        }
        .hv-generation-stage.is-processing {
            border-color: rgba(79,70,229,.36);
            background: linear-gradient(135deg, rgba(238,242,255,.98), rgba(236,253,245,.9));
            box-shadow: 0 12px 30px rgba(79,70,229,.12);
        }
        .hv-generation-stage.is-completed {
            border-color: rgba(16,185,129,.34);
            background: rgba(236,253,245,.92);
        }
        .hv-generation-stage.is-failed {
            border-color: rgba(244,63,94,.36);
            background: rgba(255,241,242,.92);
        }
        .hv-modal-toast {
            background: linear-gradient(135deg, rgba(15,23,42,.92), rgba(49,46,129,.9));
            box-shadow: 0 16px 44px rgba(0,0,0,.28);
        }
        .hv-thinking-bubble {
            position: relative;
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            box-shadow: 0 12px 30px rgba(79,70,229,.1);
        }
        .hv-thinking-bubble::after {
            content: '';
            position: absolute;
            inset-inline-start: 1.25rem;
            bottom: -7px;
            width: 14px;
            height: 14px;
            transform: rotate(45deg);
            background: #fff;
            border-inline-end: 1px solid rgba(226,232,240,.8);
            border-bottom: 1px solid rgba(226,232,240,.8);
        }
        .hv-thinking-dots span {
            animation: hv-soft-pulse 1.2s ease-in-out infinite;
        }
        .hv-thinking-dots span:nth-child(2) { animation-delay: .16s; }
        .hv-thinking-dots span:nth-child(3) { animation-delay: .32s; }
        .hv-skeleton {
            background: linear-gradient(110deg, #f4f4f5 8%, #e0e7ff 18%, #f4f4f5 33%);
            background-size: 220% 100%;
            animation: hv-skeleton 1.6s linear infinite;
        }
        .hv-upload-composer {
            overflow: hidden;
            border: 1px solid rgba(228,228,231,.92);
            border-radius: 2rem;
            background: rgba(255,255,255,.94);
            box-shadow: 0 24px 64px rgba(79,70,229,.1);
        }
        .hv-upload-workspace {
            min-width: 0;
            padding: 1rem;
        }
        .hv-upload-dropzone {
            position: relative;
            isolation: isolate;
            min-height: 18rem;
            overflow: hidden;
            border: 1.5px dashed #c7d2fe;
            border-radius: 1.6rem;
            background: linear-gradient(145deg, #fff, #fafaff 58%, #f0fdfa);
            transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
        }
        .hv-image-picker,
        .hv-image-selection-preview {
            position: relative;
            z-index: 1;
            min-height: 18rem;
        }
        .hv-image-preview-media {
            display: grid;
            min-height: 13rem;
            flex: 1 1 auto;
            place-items: center;
            overflow: hidden;
            background:
                linear-gradient(45deg, rgba(226,232,240,.45) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(226,232,240,.45) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(226,232,240,.45) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(226,232,240,.45) 75%),
                #f8fafc;
            background-position: 0 0, 0 8px, 8px -8px, -8px 0;
            background-size: 16px 16px;
        }
        .hv-image-preview-media img {
            width: 100%;
            height: 100%;
            max-height: 22rem;
            object-fit: contain;
        }
        .hv-upload-guide {
            min-width: 0;
            padding: 1rem;
            background:
                radial-gradient(circle at 15% 12%, rgba(99,102,241,.12), transparent 32%),
                linear-gradient(155deg, rgba(238,242,255,.94), rgba(236,253,245,.88));
        }
        .hv-upload-guide summary {
            display: flex;
            min-height: 3rem;
            cursor: pointer;
            list-style: none;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            border-radius: 1rem;
            outline: none;
        }
        .hv-upload-guide summary::-webkit-details-marker { display: none; }
        .hv-upload-guide summary:focus-visible {
            box-shadow: 0 0 0 3px rgba(99,102,241,.2);
        }
        .hv-upload-guide-caret {
            transition: transform .2s ease;
        }
        .hv-upload-guide[open] .hv-upload-guide-caret {
            transform: rotate(180deg);
        }
        .hv-upload-guide-content :where(p, ul, ol, blockquote) {
            margin-block: .65rem;
        }
        .hv-upload-guide-content :where(ul, ol) {
            padding-inline-start: 1.25rem;
        }
        .hv-upload-guide-content ul {
            list-style: disc;
        }
        .hv-upload-guide-content ol {
            list-style: decimal;
        }
        .hv-upload-guide-content li + li {
            margin-top: .4rem;
        }
        .hv-upload-guide-content a {
            color: #4338ca;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .hv-upload-guide-content blockquote {
            border-inline-start: 3px solid color-mix(in srgb, var(--hv-primary-color, #0f766e) 55%, #c7d2fe);
            padding-inline-start: .75rem;
            color: #52525b;
        }
        .hv-consent-row {
            min-height: 3.5rem;
            transition: border-color .2s ease, background-color .2s ease;
        }
        .hv-consent-row:has(input:checked) {
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 45%, #a7f3d0);
            background: color-mix(in srgb, var(--hv-primary-color, #0f766e) 5%, white);
        }
        .hv-action-control { min-height: 3rem; }
        .hv-otp-brand {
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 22%, white);
            background:
                radial-gradient(circle at 12% 10%, color-mix(in srgb, var(--hv-primary-color, #0f766e) 18%, transparent), transparent 34%),
                linear-gradient(135deg, #ffffff, #f8fafc);
        }
        .hv-treatment-card {
            cursor: pointer;
            border: 1px solid rgba(228,228,231,.9);
            background:
                linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.9)),
                linear-gradient(135deg, color-mix(in srgb, var(--hv-primary-color, #0f766e) 10%, transparent), rgba(99,102,241,.08));
            transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .hv-treatment-card.is-selected {
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 44%, #a5b4fc);
            box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }
        .hv-output-option {
            min-height: 7.5rem;
            border: 1px solid #e4e4e7;
            color: #27272a;
            background: #fff;
            transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .hv-output-option.is-selected {
            border: 2px solid transparent;
            color: #fff;
            background:
                linear-gradient(135deg, #4f46e5, #4338ca 54%, #0f766e) padding-box,
                linear-gradient(110deg, #5eead4, #818cf8, #c084fc, #5eead4) border-box;
            background-size: 100% 100%, 220% 100%;
            box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }
        @media (hover: hover) and (pointer: fine) {
            .hv-button-primary:hover:not(:disabled),
            .hv-primary-action:hover:not(:disabled) {
                filter: brightness(1.045) saturate(1.06);
                box-shadow: 0 0 0 3px rgba(99,102,241,.1), 0 7px 18px rgba(67,56,202,.18);
            }
            .hv-button-secondary:hover:not(:disabled),
            .hv-secondary-action:hover:not(:disabled),
            .hv-lift:hover:not(:disabled) {
                border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 48%, #a5b4fc);
                color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 82%, #18181b);
                background: color-mix(in srgb, var(--hv-primary-color, #0f766e) 7%, white);
                box-shadow: 0 0 0 3px rgba(99,102,241,.07);
            }
            .hv-button-on-dark:hover:not(:disabled) {
                border-color: #fff;
                background: #fff;
                box-shadow: 0 0 0 3px rgba(255,255,255,.16), 0 5px 16px rgba(0,0,0,.16);
            }
            .hv-treatment-card:hover,
            .hv-output-option:hover {
                border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 40%, #a5b4fc);
                background-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 4%, white);
                box-shadow: 0 0 0 3px rgba(99,102,241,.07);
            }
            .hv-treatment-card:hover .hv-service-visual,
            .hv-result-card:hover .hv-result-image {
                filter: saturate(1.08) contrast(1.03);
            }
        }
        .hv-treatment-icon {
            background: linear-gradient(135deg, var(--hv-primary-color, #0f766e), #4f46e5 56%, #c026d3);
        }
        #landingApp button:not(:disabled),
        #imageModal button:not(:disabled),
        #imageModal a,
        [data-gallery-index] {
            cursor: pointer;
        }
        #landingApp button:disabled,
        #imageModal button:disabled {
            cursor: not-allowed;
        }
        .hv-button:focus-visible,
        #landingApp :where(button, a, label[for]):focus-visible,
        #imageModal :where(button, a):focus-visible {
            outline: 3px solid color-mix(in srgb, var(--hv-primary-color, #0f766e) 58%, #6366f1);
            outline-offset: 3px;
        }
        @media (min-width: 640px) {
            .hv-step-caption { display: block; }
            .hv-step-title { font-size: .82rem; }
        }
        @media (min-width: 1024px) {
            .hv-upload-composer {
                display: grid;
                grid-template-columns: minmax(17rem, 1fr) minmax(0, 2fr);
                align-items: stretch;
            }
            .hv-upload-workspace {
                border-inline-start: 1px solid rgba(224,231,255,.92);
                padding: 1.25rem;
            }
            .hv-upload-guide {
                padding: 1.25rem;
            }
            .hv-upload-guide summary {
                cursor: default;
            }
            .hv-upload-guide-caret {
                display: none;
            }
        }
        @media (max-width: 639px) {
            .hv-journey-progress {
                border-radius: 1.25rem;
                padding: .65rem;
            }
            .hv-stepper { gap: .3rem; }
            .hv-step {
                min-height: 3.35rem;
                flex-direction: column;
                justify-content: center;
                gap: .25rem;
                padding: .4rem .2rem;
                text-align: center;
            }
            .hv-step-marker {
                width: 1.6rem;
                height: 1.6rem;
                border-radius: .65rem;
            }
            .hv-step-title { font-size: .68rem; }
            .hv-upload-workspace,
            .hv-upload-guide { padding: .85rem; }
            .hv-upload-guide {
                border-bottom: 1px solid rgba(199,210,254,.72);
            }
            .hv-upload-dropzone,
            .hv-image-picker,
            .hv-image-selection-preview {
                min-height: 15.5rem;
            }
            .hv-image-preview-media { min-height: 11.5rem; }
            #outputChoicePanel {
                border-radius: 1.25rem;
                padding: .75rem;
            }
            #outputOptions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: .4rem;
            }
            .hv-output-option {
                display: grid;
                min-height: 3rem;
                place-items: center;
                padding: .45rem .3rem;
                text-align: center;
            }
            .hv-output-title {
                overflow-wrap: anywhere;
                font-size: .7rem;
                line-height: 1.45;
            }
            .hv-output-description,
            .hv-output-status {
                display: none;
            }
            .hv-mobile-action {
                width: 100%;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .hv-enter,
            .hv-ai-cast,
            .hv-live-dot,
            .hv-button-primary,
            .hv-primary-action,
            .hv-ai-input,
            .hv-skeleton,
            .hv-thinking-dots span {
                animation: none !important;
            }
            .hv-button,
            .hv-primary-action,
            .hv-secondary-action,
            .hv-lift,
            .hv-treatment-card,
            .hv-output-option,
            .hv-step::before,
            .hv-service-visual,
            .hv-result-card img,
            .hv-modal-image,
            .hv-upload-guide-caret {
                transition: none !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-[#eef2ff] font-sans text-zinc-900 antialiased" style="--hv-primary-color: <?php echo e($primaryColor); ?>;" data-landing-template="<?php echo e($landingTheme['template_code']); ?>">
    <?php echo $landingTheme['tracking_body_code'] ?? ''; ?>

    <div class="flex min-h-screen flex-col">
        <header class="w-full bg-white/85 shadow-sm shadow-zinc-200/60 backdrop-blur z-10">
            <?php echo $landingTheme['header_html']; ?>

        </header>

        <main class="w-full flex-1">
            <?php if(! empty($landingTheme['body_html'])): ?>
                <section data-landing-template-body>
                    <?php echo $landingTheme['body_html']; ?>
                </section>
            <?php endif; ?>
            <section class="relative isolate overflow-hidden px-4 py-6 sm:px-6 lg:py-8">
                <div class="absolute inset-0 -z-10 bg-[linear-gradient(135deg,#ecfeff_0%,#f8fafc_32%,#eef2ff_68%,#faf5ff_100%)]"></div>

                <div id="landingApp" class="mx-auto w-full max-w-7xl px-4">
                    <section>
                        <?php if(! empty($landingTheme['back_link_url']) && ! $landingTheme['renders_back_link_in_template']): ?>
                            <div class="mb-4 flex justify-end">
                                <a href="<?php echo e($landingTheme['back_link_url']); ?>" target="<?php echo e($landingTheme['back_link_target']); ?>"<?php if($landingTheme['back_link_target'] === '_blank'): ?> rel="noopener noreferrer"<?php endif; ?> class="hv-button hv-button-secondary hv-button-compact inline-flex">
                                    <?php echo e($landingTheme['back_link_title'] ?? 'بازگشت به سایت'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="space-y-4 sm:space-y-5">
                            <div id="journeyProgress" class="hv-journey-progress space-y-2 rounded-2xl p-3 sm:space-y-3 sm:rounded-3xl sm:p-4">
                                <div class="flex items-center justify-between gap-3 text-xs font-bold">
                                    <span id="stepProgressLabel" class="text-zinc-700">مرحله ۱ از ۴</span>
                                    <span class="text-zinc-500">کمتر از چند دقیقه</span>
                                </div>
                                <div id="stepProgressTrack" class="h-1.5 overflow-hidden rounded-full bg-zinc-200/80" role="progressbar" aria-label="پیشرفت مراحل" aria-valuemin="1" aria-valuemax="4" aria-valuenow="1">
                                    <div id="stepProgress" class="h-full w-1/4 rounded-full bg-linear-to-l from-emerald-500 via-indigo-500 to-fuchsia-500 transition-all duration-500"></div>
                                </div>
                                <ol class="hv-stepper" aria-label="مراحل پیش‌نمایش">
                                    <li data-step-indicator="1" class="step-indicator hv-step hv-step-active" aria-current="step">
                                        <span class="step-marker hv-step-marker text-white">۱</span>
                                        <span class="hv-step-copy">
                                            <span class="step-label hv-step-title">کاربرد</span>
                                            <span class="step-caption hv-step-caption text-white/70">نوع درمان</span>
                                        </span>
                                    </li>
                                    <li data-step-indicator="2" class="step-indicator hv-step hv-step-upcoming">
                                        <span class="step-marker hv-step-marker text-zinc-500">۲</span>
                                        <span class="hv-step-copy">
                                            <span class="step-label hv-step-title">تصویر</span>
                                            <span class="step-caption hv-step-caption text-zinc-500">انتخاب عکس</span>
                                        </span>
                                    </li>
                                    <li data-step-indicator="3" class="step-indicator hv-step hv-step-upcoming">
                                        <span class="step-marker hv-step-marker text-zinc-500">۳</span>
                                        <span class="hv-step-copy">
                                            <span class="step-label hv-step-title">تایید</span>
                                            <span class="step-caption hv-step-caption text-zinc-500">شماره موبایل</span>
                                        </span>
                                    </li>
                                    <li data-step-indicator="4" class="step-indicator hv-step hv-step-upcoming">
                                        <span class="step-marker hv-step-marker text-zinc-500">۴</span>
                                        <span class="hv-step-copy">
                                            <span class="step-label hv-step-title">نتیجه</span>
                                            <span class="step-caption hv-step-caption text-zinc-500">ساخت تصویر</span>
                                        </span>
                                    </li>
                                </ol>
                            </div>

                            <section class="flow-step hv-enter mb-0! space-y-4 rounded-3xl bg-white p-4 shadow-xl shadow-indigo-100/60 sm:space-y-5 sm:p-5" id="step1" aria-labelledby="step1Title">
                                <div class="hv-otp-brand rounded-3xl border p-4">
                                    <p class="text-sm font-bold" style="color: var(--hv-primary-color);"><?php echo e($tenant->name ?? 'HairVision'); ?></p>
                                    <h2 id="step1Title" class="mt-1 text-xl font-bold text-zinc-950">برای چه تغییری پیش‌نمایش می‌خواهید؟</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">گزینه‌ها بر اساس سرویس‌های فعال همین کلینیک نمایش داده می‌شوند.</p>
                                </div>

                                <div id="categoryOptions" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>
                                <div id="noApplicationsPanel" class="hidden rounded-3xl bg-zinc-50 p-5 text-sm leading-7 text-zinc-600 ring-1 ring-zinc-200">
                                    در حال حاضر این کلینیک کاربرد فعال قابل درخواست ندارد.
                                </div>

                                <p id="treatmentError" class="hidden text-sm text-rose-600"></p>
                                <div class="flex justify-end">
                                    <button id="continueToImage" type="button" class="hv-button hv-button-primary hv-action-control hv-mobile-action inline-flex min-w-44" disabled>تایید و ادامه</button>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-4 rounded-3xl bg-white p-4 shadow-xl shadow-indigo-100/60 sm:space-y-5 sm:p-5" id="step2" aria-labelledby="step2Title">
                                <div class="flex flex-wrap items-end justify-between gap-3">
                                    <div class="max-w-2xl">
                                        <p class="text-sm font-bold text-indigo-600">یک عکس واضح، یک پیش‌نمایش دقیق‌تر</p>
                                        <h2 id="step2Title" class="mt-1 text-xl font-bold text-zinc-950">تصویر مناسب را انتخاب کنید</h2>
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">عکس شما بعد از تایید شماره، خصوصی و فقط برای همین کلینیک ذخیره می‌شود.</p>
                                    </div>
                                    <span id="selectedUploadCategory" class="inline-flex min-h-9 items-center rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 ring-1 ring-indigo-100"></span>
                                </div>

                                <div class="hv-upload-composer">
                                    <details id="uploadGuidePanel" class="hv-upload-guide" aria-labelledby="uploadGuideTitle" open>
                                        <summary>
                                            <span class="flex min-w-0 items-center gap-3">
                                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-white text-indigo-700 shadow-sm ring-1 ring-indigo-100" aria-hidden="true">
                                                    <span class="fi fi-rr-check h-5 w-5"></span>
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block text-xs font-bold text-indigo-600">راهنمای عکس مناسب</span>
                                                    <span id="uploadGuideTitle" class="mt-0.5 block text-sm font-bold leading-6 text-zinc-950">نکات عکس را ببینید</span>
                                                </span>
                                            </span>
                                            <span class="hv-upload-guide-caret fi fi-rr-angle-small-down h-5 w-5 shrink-0 text-indigo-700" aria-hidden="true"></span>
                                        </summary>
                                        <div id="uploadGuideContent" class="hv-upload-guide-content mt-3 text-sm leading-7 text-zinc-700" aria-live="polite"></div>
                                    </details>

                                    <div class="hv-upload-workspace space-y-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <p id="imageInputLabel" class="text-sm font-bold text-zinc-900">تصویر برای پیش‌نمایش</p>
                                            <span class="text-xs text-zinc-500">فقط یک فایل</span>
                                        </div>
                                        <div id="uploadDropZone" class="hv-upload-dropzone">
                                            <input id="image" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="sr-only" aria-labelledby="imageInputLabel" aria-describedby="imageRequirements imagePreparationStatus uploadError" />
                                            <label id="imagePicker" for="image" class="hv-image-picker group flex cursor-pointer flex-col items-center justify-center p-5 text-center">
                                                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-linear-to-br from-indigo-700 via-indigo-600 to-emerald-700 text-white shadow-sm shadow-indigo-200" aria-hidden="true">
                                                    <span class="fi fi-rr-upload h-6 w-6"></span>
                                                </span>
                                                <span class="mt-4 block text-base font-bold text-zinc-950">برای انتخاب عکس لمس کنید</span>
                                                <span class="mt-1 hidden text-sm text-zinc-500 sm:block">یا تصویر را اینجا بکشید و رها کنید</span>
                                                <span id="imageRequirements" class="mt-3 inline-flex rounded-full bg-white px-3 py-1.5 text-xs font-bold text-zinc-500 ring-1 ring-zinc-200">JPG، PNG، WEBP، HEIC یا HEIF تا ۵ مگابایت</span>
                                                <span class="mt-2 block text-xs leading-6 text-zinc-500">تصاویر آیفون پیش از ارسال، داخل مرورگر به JPG تبدیل می‌شوند.</span>
                                            </label>

                                            <div id="imageSelectionPreview" class="hv-image-selection-preview hidden flex-col" aria-live="polite">
                                                <div class="hv-image-preview-media">
                                                    <img id="imagePreview" alt="پیش‌نمایش کامل تصویر انتخاب‌شده" />
                                                </div>
                                                <div class="relative z-10 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-white/95 p-3">
                                                    <div class="min-w-0">
                                                        <p id="imageFileName" class="max-w-64 truncate text-sm font-bold text-zinc-900">تصویر انتخاب‌شده</p>
                                                        <p id="imageFileStatus" class="mt-0.5 text-xs text-emerald-700">آماده ادامه مسیر</p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button id="viewSelectedImage" type="button" class="hv-button hv-button-secondary hv-button-compact hv-action-control inline-flex">
                                                            <span class="fi fi-rr-zoom-in h-4 w-4" aria-hidden="true"></span>
                                                            مشاهده
                                                        </button>
                                                        <label for="image" class="hv-button hv-button-secondary hv-button-compact hv-action-control inline-flex cursor-pointer">تغییر عکس</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <p id="imagePreparationStatus" class="hidden rounded-2xl bg-indigo-50 px-3.5 py-3 text-sm font-bold leading-6 text-indigo-800 ring-1 ring-indigo-100" role="status" aria-live="polite"></p>

                                        <div class="space-y-3">
                                            <label for="consent" class="hv-consent-row flex cursor-pointer items-start gap-3 rounded-2xl border border-zinc-200 bg-zinc-50/75 p-3.5">
                                                <input id="consent" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                                                <span class="text-sm leading-7 text-zinc-700"><?php echo e($landingTheme['consent_text']); ?></span>
                                            </label>
                                            <div class="flex items-start gap-2 rounded-2xl bg-emerald-50/70 px-3.5 py-3 text-xs leading-6 text-emerald-900 ring-1 ring-emerald-100">
                                                <span class="fi fi-rr-lock mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"></span>
                                                <span>تصویر عمومی نمی‌شود و مسیر ذخیره‌سازی آن در اختیار بازدیدکننده قرار نمی‌گیرد.</span>
                                            </div>
                                            <p id="uploadError" class="hidden rounded-2xl bg-rose-50 px-3.5 py-3 text-sm font-bold leading-6 text-rose-700 ring-1 ring-rose-100" role="alert" aria-live="assertive"></p>
                                            <div class="flex flex-col gap-2 sm:flex-row">
                                                <button id="continueToPhone" type="button" class="hv-button hv-button-primary hv-action-control inline-flex flex-1" disabled>ادامه و تایید شماره</button>
                                                <button id="backToTreatment" type="button" class="hv-button hv-button-secondary hv-action-control inline-flex min-w-28">بازگشت</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-4 rounded-3xl bg-white p-4 shadow-xl shadow-indigo-100/60 sm:space-y-5 sm:p-5" id="step3" aria-labelledby="step3Title">
                                <div class="hv-otp-brand rounded-3xl border p-4">
                                    <p class="text-sm font-bold" style="color: var(--hv-primary-color);">تایید امن شماره</p>
                                    <h2 id="step3Title" class="mt-1 text-xl font-bold text-zinc-950">شماره موبایل را تایید کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">بعد از تایید OTP، تصویر ذخیره می‌شود و اگر چند خروجی فعال باشد، خروجی موردنظر را همین‌جا انتخاب می‌کنید.</p>
                                </div>

                                <div id="phoneEntryPanel" class="max-w-xl">
                                    <div>
                                        <label for="phone" class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                                        <input id="phone" type="text" inputmode="numeric" autocomplete="tel" maxlength="11" pattern="[0-9۰-۹٠-٩]*" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-right text-base outline-none transition" />
                                        <p id="phoneError" class="mt-2 hidden text-sm text-rose-600"></p>
                                        <button id="requestOtp" type="button" class="hv-button hv-button-primary hv-action-control hv-mobile-action mt-3 inline-flex min-w-40">ارسال کد تایید</button>
                                    </div>
                                </div>

                                <div id="otpEntryPanel" class="hidden max-w-xl space-y-4">
                                    <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200">
                                        <p class="text-xs font-bold text-zinc-500">شماره تاییدشده برای دریافت کد</p>
                                        <p id="lockedPhone" class="mt-1 text-lg font-bold text-zinc-950" dir="ltr"></p>
                                    </div>
                                    <div>
                                        <label for="otp" class="block text-sm font-bold text-zinc-800">کد تایید</label>
                                        <input id="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9۰-۹٠-٩]*" placeholder="۱۲۳۴۵۶" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-center text-lg font-bold tracking-[0.3em] outline-none transition" dir="ltr" />
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">کد ارسال شده به <span id="displayPhone" class="font-bold text-zinc-900"></span></p>
                                        <p id="otpDebug" class="mt-2 hidden text-sm text-sky-700"></p>
                                        <p id="otpError" class="mt-2 hidden text-sm text-rose-600"></p>
                                    </div>
                                </div>

                                <div class="hidden rounded-2xl bg-zinc-50 p-3 ring-1 ring-zinc-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span id="otpTimerText" class="font-bold text-zinc-700">زمان باقی‌مانده: <span id="otpCountdown" dir="ltr">۰۲:۰۰</span></span>
                                        <button id="resendOtp" type="button" class="hv-button hv-button-secondary hv-button-compact inline-flex" disabled>ارسال مجدد کد</button>
                                    </div>
                                </div>

                                <p id="mobileError" class="hidden text-sm text-rose-600"></p>

                                <div id="otpActionPanel" class="hidden max-w-xl flex-wrap gap-3">
                                    <button id="verifyOtp" type="button" class="hv-button hv-button-primary hv-action-control hv-mobile-action inline-flex min-w-44">تایید و شروع درخواست</button>
                                    <button id="backToPhone" type="button" class="hv-button hv-button-secondary hv-action-control hv-mobile-action inline-flex min-w-32">تغییر شماره</button>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-4 rounded-3xl bg-white p-4 shadow-xl shadow-indigo-100/60 sm:space-y-5 sm:p-5" id="step4" aria-labelledby="step4Title">
                                <div class="flex flex-wrap items-end justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-bold text-indigo-600">انتخاب خروجی و مشاهده نتیجه</p>
                                        <h2 id="step4Title" class="mt-2 text-xl font-bold text-zinc-950">خروجی موردنظر را انتخاب کنید</h2>
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">هر خروجی آماده شود همان لحظه نمایش داده می‌شود؛ می‌توانید خروجی دیگری از همین خدمت را هم به صف اضافه کنید.</p>
                                    </div>
                                    <button id="newImageUpload" type="button" class="hv-button hv-button-secondary hv-button-compact hv-mobile-action inline-flex">
                                        <span class="fi fi-rr-upload h-4 w-4" aria-hidden="true"></span>
                                        <span>آپلود تصویر جدید</span>
                                    </button>
                                </div>
                                <div id="outputChoicePanel" class="rounded-3xl bg-zinc-50 p-4 ring-1 ring-zinc-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-bold text-zinc-900">پیشنهادهای قابل ساخت</p>
                                            <p class="mt-1 text-xs leading-6 text-zinc-500">روی هر خروجی بزنید تا همان تصویر وارد صف شود؛ خروجی‌های دیگر تا زمان انتخاب خالی می‌مانند.</p>
                                        </div>
                                        <span id="outputChoiceCount" class="rounded-full bg-white px-3 py-1 text-xs font-bold text-zinc-500 ring-1 ring-zinc-200"></span>
                                    </div>
                                    <div id="outputOptions" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4"></div>
                                    <button id="confirmOutputRequest" type="button" class="hv-button hv-button-primary mt-4 hidden w-full" disabled>تایید و ساخت خروجی انتخاب‌شده</button>
                                </div>
                                <p id="generationError" class="hidden text-sm text-rose-600"></p>
                                <div id="resultsContainer" class="hidden grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4"></div>
                            </section>
                        </div>
                    </section>
                </div>
            </section>
        </main>

        @include('public.partials.copyright')

        <footer class="w-full bg-white/85 shadow-[0_-1px_20px_rgba(24,24,27,0.04)] backdrop-blur">
            <?php echo $landingTheme['footer_html']; ?>

        </footer>
    </div>

    <div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/85 p-4 backdrop-blur">
        <button id="closeImageModal" class="hv-button hv-button-on-dark hv-button-compact absolute left-4 top-4 inline-flex" aria-label="بستن تصویر">
            <span class="fi fi-rr-cross-small h-4 w-4" aria-hidden="true"></span>
            <span>بستن</span>
        </button>
        <a id="downloadImageModal" class="hv-button hv-button-on-dark hv-button-compact absolute right-4 top-4 hidden items-center" href="#" download data-gallery-download>
            <span class="fi fi-rr-arrow-down h-4 w-4" aria-hidden="true"></span>
            <span>دانلود</span>
        </a>
        <button id="prevImageModal" class="hv-button hv-button-on-dark hv-button-icon absolute right-4 top-1/2 grid -translate-y-1/2 place-items-center text-xl sm:right-8" aria-label="تصویر قبلی">
            <span class="fi fi-rr-arrow-small-right h-5 w-5" aria-hidden="true"></span>
        </button>
        <figure class="w-full max-w-5xl">
            <div id="modalSkeleton" class="hv-skeleton mx-auto hidden aspect-4/5 max-h-[82vh] w-full max-w-xl rounded-3xl shadow-2xl"></div>
            <img id="modalImage" alt="نمای بزرگ تصویر" class="hv-modal-image mx-auto max-h-[82vh] max-w-full rounded-3xl object-contain shadow-2xl" />
            <figcaption id="modalCaption" class="mt-4 text-center text-sm font-bold text-white"></figcaption>
        </figure>
        <p id="modalToast" class="hv-modal-toast absolute bottom-4 left-4 right-4 hidden rounded-2xl px-4 py-3 text-center text-sm font-bold leading-7 text-white sm:left-1/2 sm:right-auto sm:w-full sm:max-w-xl sm:-translate-x-1/2"></p>
        <button id="nextImageModal" class="hv-button hv-button-on-dark hv-button-icon absolute left-4 top-1/2 grid -translate-y-1/2 place-items-center text-xl sm:left-8" aria-label="تصویر بعدی">
            <span class="fi fi-rr-arrow-small-left h-5 w-5" aria-hidden="true"></span>
        </button>
    </div>

<?php
    $generationFrontend = [
        'messages' => __('ai.generation.messages'),
        'statuses' => \App\Support\StateCatalog::options('generation.statuses'),
        'serviceVisuals' => [
            'hair_transplant' => $hairConsultationHref,
            'eyebrow_transplant' => $faceConsultationHref,
            'default' => $faceConsultationHref,
        ],
        'messageIntervalMs' => config('hairvision.generation.message_interval_ms'),
        'pollIntervalMs' => config('hairvision.generation.poll_interval_ms'),
        'pollInitialIntervalMs' => config('hairvision.image_queue.poll_initial_interval_ms'),
        'pollMaxIntervalMs' => config('hairvision.image_queue.poll_max_interval_ms'),
        'softTimeoutMs' => config('hairvision.generation.soft_timeout_ms'),
        'analytics' => [
            'visitToken' => $landingAnalyticsToken,
            'endpoint' => '/api/v1/landing/events',
        ],
    ];
?>
<script>
    window.HAIRVISION_GENERATION_CONFIG = <?php echo json_encode($generationFrontend, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
</body>
</html>
