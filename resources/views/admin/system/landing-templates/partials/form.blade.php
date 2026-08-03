{{-- @since 2.1.0 Adds SEO, tracker, and safe clinic-link variable inputs. --}}
@php
    $landingTemplate = $landingTemplate ?? null;
    $defaults = $templateDefaults;
    $isEdit = $landingTemplate !== null;
@endphp

<x-admin.validation-summary :for="$isEdit ? 'edit_landing_template' : 'create_landing_template'" />

<div class="grid gap-4 lg:grid-cols-2">
    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.name') }}</span>
        <input name="name" value="{{ old('name', $landingTemplate?->name) }}" required class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="قالب کلینیک مدرن">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.code') }}</span>
        <input
            name="code"
            value="{{ old('code', $landingTemplate?->code) }}"
            @readonly($isEdit)
            @disabled($isEdit)
            required
            dir="ltr"
            class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 disabled:bg-zinc-100 disabled:text-zinc-500"
            placeholder="modern-clinic"
        >
    </label>

    <label class="grid gap-1 text-sm font-bold lg:col-span-2">
        <span>{{ __('landing_templates.fields.description') }}</span>
        <textarea name="description" rows="3" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500" placeholder="کاربرد و ویژگی‌های ظاهری این قالب">{{ old('description', $landingTemplate?->description) }}</textarea>
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.primary_color') }}</span>
        <div class="flex items-center gap-2">
            <input name="primary_color" value="{{ old('primary_color', $landingTemplate?->primary_color ?? $defaults['primary_color']) }}" required dir="ltr" class="h-11 min-w-0 flex-1 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="#0f766e">
            <input type="color" value="{{ old('primary_color', $landingTemplate?->primary_color ?? $defaults['primary_color']) }}" class="h-11 w-12 rounded-lg border border-zinc-200 bg-white p-1" aria-label="{{ __('landing_templates.fields.primary_color') }}" data-landing-template-color>
        </div>
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.status') }}</span>
        <select name="is_active" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            <option value="1" @selected((bool) old('is_active', $landingTemplate?->is_active ?? true))>{{ __('landing_templates.statuses.active') }}</option>
            <option value="0" @selected(! (bool) old('is_active', $landingTemplate?->is_active ?? true)) @disabled($landingTemplate?->is_default)>{{ __('landing_templates.statuses.inactive') }}</option>
        </select>
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.favicon_path') }}</span>
        <input name="favicon_path" value="{{ old('favicon_path', $landingTemplate?->favicon_path) }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="/favicon.ico">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.consent_text') }}</span>
        <input name="consent_text" value="{{ old('consent_text', $landingTemplate?->consent_text ?? $defaults['consent_text']) }}" required class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.back_link_title_fallback') }}</span>
        <input name="back_link_title" value="{{ old('back_link_title', $landingTemplate?->back_link_title ?? $defaults['back_link_title']) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="بازگشت به سایت کلینیک">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.back_link_url_fallback') }}</span>
        <input name="back_link_url" value="{{ old('back_link_url', $landingTemplate?->back_link_url ?? $defaults['back_link_url']) }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="https://clinic.example">
    </label>
</div>

<section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
    <div>
        <h3 class="text-base font-bold text-zinc-950">{{ __('landing_templates.seo_title') }}</h3>
        <p class="mt-1 text-sm leading-7 text-zinc-600">{{ __('landing_templates.seo_hint') }}</p>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <label class="grid gap-1 text-sm font-bold">
            <span>{{ __('landing_templates.fields.seo_title') }}</span>
            <input name="seo_title" value="{{ old('seo_title', $landingTemplate?->seo_title ?? $defaults['seo_title']) }}" maxlength="70" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="پیش‌نمایش هوشمند کاشت مو">
            <span class="text-xs font-normal leading-6 text-zinc-500">{{ __('landing_templates.seo_title_hint') }}</span>
        </label>

        <label class="grid gap-1 text-sm font-bold">
            <span>{{ __('landing_templates.fields.seo_robots') }}</span>
            <select name="seo_robots" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                @foreach(__('landing_templates.robots') as $value => $label)
                    <option value="{{ $value }}" @selected(old('seo_robots', $landingTemplate?->seo_robots ?? $defaults['seo_robots']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm font-bold lg:col-span-2">
            <span>{{ __('landing_templates.fields.seo_description') }}</span>
            <textarea name="seo_description" rows="3" maxlength="320" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500" placeholder="توضیح کوتاه و دقیق درباره صفحه و خدمت کلینیک">{{ old('seo_description', $landingTemplate?->seo_description ?? $defaults['seo_description']) }}</textarea>
        </label>

        <label class="grid gap-1 text-sm font-bold lg:col-span-2">
            <span>{{ __('landing_templates.fields.seo_image_path') }}</span>
            <input name="seo_image_path" value="{{ old('seo_image_path', $landingTemplate?->seo_image_path ?? $defaults['seo_image_path']) }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="/theme/clinic-social-cover.jpg">
            <span class="text-xs font-normal leading-6 text-zinc-500">{{ __('landing_templates.seo_image_hint') }}</span>
        </label>
    </div>
</section>

<section class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
    <div>
        <h3 class="text-base font-bold text-indigo-950">{{ __('landing_templates.tracking_title') }}</h3>
        <p class="mt-1 text-sm leading-7 text-indigo-800">{{ __('landing_templates.tracking_hint') }}</p>
        <p class="mt-2 text-xs font-bold leading-6 text-amber-800">{{ __('landing_templates.tracking_privacy_warning') }}</p>
    </div>

    <div class="mt-4 grid gap-4">
        <label class="grid gap-2 text-sm font-bold">
            <span>{{ __('landing_templates.fields.tracking_head_code') }}</span>
            <textarea name="tracking_head_code" rows="8" dir="ltr" spellcheck="false" data-landing-template-code-editor class="admin-code-editor w-full rounded-lg border px-4 py-3 outline-none" placeholder="<!-- Google tag or another tracker head snippet -->">{{ old('tracking_head_code', $landingTemplate?->tracking_head_code ?? $defaults['tracking_head_code']) }}</textarea>
        </label>

        <label class="grid gap-2 text-sm font-bold">
            <span>{{ __('landing_templates.fields.tracking_body_code') }}</span>
            <textarea name="tracking_body_code" rows="6" dir="ltr" spellcheck="false" data-landing-template-code-editor class="admin-code-editor w-full rounded-lg border px-4 py-3 outline-none" placeholder="<!-- Optional noscript/body tracker snippet -->">{{ old('tracking_body_code', $landingTemplate?->tracking_body_code ?? $defaults['tracking_body_code']) }}</textarea>
        </label>
    </div>
</section>

<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-7 text-amber-900">
    <p class="font-bold">{{ __('landing_templates.trusted_code_warning') }}</p>
    <p>{{ __('landing_templates.code_editor_hint') }}</p>
</div>

<section class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-4 text-sm text-teal-950">
    <div class="flex items-start gap-3">
        <x-admin.icon name="link" class="mt-0.5 h-5 w-5 shrink-0 text-teal-700" />
        <div class="min-w-0 flex-1">
            <h3 class="font-bold">{{ __('landing_templates.variable_guide_title') }}</h3>
            <p class="mt-1 leading-7 text-teal-800">{{ __('landing_templates.variable_guide_hint') }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-3 lg:grid-cols-3">
        @foreach($templateVariables as $variable)
            <div class="rounded-lg bg-white p-3 ring-1 ring-teal-200">
                <p class="font-bold text-zinc-800">{{ $variable['label'] }}</p>
                <code class="admin-template-variable-code mt-2 block overflow-x-auto rounded-md px-3 py-2 text-left text-xs" dir="ltr">{{ $variable['token'] }}</code>
                <p class="mt-2 text-xs leading-6 text-zinc-600">{{ $variable['usage'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        <p class="font-bold">{{ __('landing_templates.variable_example_title') }}</p>
        <pre class="admin-template-variable-code mt-2 overflow-x-auto rounded-lg px-4 py-3 text-left text-xs leading-6" dir="ltr"><code>{{ $backLinkVariableExample }}</code></pre>
        <p class="mt-2 text-xs leading-6 text-teal-800">{{ __('landing_templates.variable_security_hint') }}</p>
    </div>
</section>

<label class="grid gap-2 text-sm font-bold">
    <span>{{ __('landing_templates.fields.header_html') }}</span>
    <textarea name="header_html" rows="16" required dir="ltr" spellcheck="false" data-landing-template-code-editor class="admin-code-editor w-full rounded-lg border px-4 py-3 outline-none">{{ old('header_html', $landingTemplate?->header_html ?? $defaults['header_html']) }}</textarea>
</label>

<label class="grid gap-2 text-sm font-bold">
    <span>{{ __('landing_templates.fields.body_html') }}</span>
    <textarea name="body_html" rows="10" dir="ltr" spellcheck="false" data-landing-template-code-editor class="admin-code-editor w-full rounded-lg border px-4 py-3 outline-none">{{ old('body_html', $landingTemplate?->body_html ?? $defaults['body_html']) }}</textarea>
</label>

<label class="grid gap-2 text-sm font-bold">
    <span>{{ __('landing_templates.fields.footer_html') }}</span>
    <textarea name="footer_html" rows="16" required dir="ltr" spellcheck="false" data-landing-template-code-editor class="admin-code-editor w-full rounded-lg border px-4 py-3 outline-none">{{ old('footer_html', $landingTemplate?->footer_html ?? $defaults['footer_html']) }}</textarea>
    <span class="text-xs font-normal leading-6 text-zinc-600">{{ __('landing_templates.system_copyright_notice') }}</span>
</label>

<div class="flex flex-wrap justify-end gap-2">
    <x-admin.button :href="route('admin.system.landing-templates.index')" variant="secondary">{{ __('messages.cancel') }}</x-admin.button>
    <x-admin.button type="submit" icon="check">{{ __('messages.save') }}</x-admin.button>
</div>
