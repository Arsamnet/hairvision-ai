@php
    $landingTemplate = $landingTemplate ?? null;
    $defaults = \App\Support\LandingTemplateDefaults::content();
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
        <span>{{ __('landing_templates.fields.back_link_title') }}</span>
        <input name="back_link_title" value="{{ old('back_link_title', $landingTemplate?->back_link_title) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="بازگشت به سایت کلینیک">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('landing_templates.fields.back_link_url') }}</span>
        <input name="back_link_url" value="{{ old('back_link_url', $landingTemplate?->back_link_url) }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="https://clinic.example">
    </label>
</div>

<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-7 text-amber-900">
    <p class="font-bold">{{ __('landing_templates.trusted_code_warning') }}</p>
    <p>{{ __('landing_templates.code_editor_hint') }}</p>
</div>

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
</label>

<div class="flex flex-wrap justify-end gap-2">
    <x-admin.button :href="route('admin.system.landing-templates.index')" variant="secondary">{{ __('messages.cancel') }}</x-admin.button>
    <x-admin.button type="submit" icon="check">{{ __('messages.save') }}</x-admin.button>
</div>
