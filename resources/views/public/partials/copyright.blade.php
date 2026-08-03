{{-- @since 2.1.0 Introduces the immutable HairVision copyright strip. --}}
<section class="w-full border-y border-zinc-200 bg-white px-4 py-4 text-zinc-600" aria-label="{{ __('landing_templates.system_copyright_label') }}" data-hairvision-copyright>
    <div class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 text-center px-4 sm:flex-row sm:text-start">
        <p class="text-xs leading-6 sm:text-sm">{{ __('landing_templates.system_copyright') }}</p>
        <div class="inline-flex items-center gap-2 text-xs font-bold text-zinc-500 sm:text-sm">
            <span>{{ __('landing_templates.system_powered_by') }}</span>
            <a href="{{ rtrim((string) config('app.url'), '/') }}" target="_blank" class="inline-flex min-h-11 items-center rounded-xl focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-violet-600" aria-label="{{ __('landing_templates.system_copyright_logo_link') }}">
                <img src="/theme/admin-logo-light.svg" alt="HairVision" class="h-8 w-auto max-w-40">
            </a>
        </div>
    </div>
</section>
