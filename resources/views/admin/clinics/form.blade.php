{{-- @since 2.1.0 Moves active landing-template assignment into clinic editing. --}}
@php
    $clinic = $clinic ?? null;
    $primaryDomain = $primaryDomain ?? null;
    $formKey = $formKey ?? 'clinic_form';
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
<x-admin.validation-summary :for="$formKey" class="md:col-span-2" />

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.name') }}</span>
    <input name="name" value="{{ old('name', $clinic?->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.slug') }}</span>
    <input name="slug" value="{{ old('slug', $clinic?->slug) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.status') }}</span>
    <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
        @foreach(\App\Support\StateCatalog::options('tenants.statuses') as $status => $label)
            <option value="{{ $status }}" @selected(old('status', $clinic?->status ?? 'active') === $status)>{{ $label }}</option>
        @endforeach
    </select>
</label>

@if($clinic)
    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('clinics.fields.landing_template') }}</span>
        <select name="template_id" required class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            <option value="">{{ __('clinics.select_landing_template') }}</option>
            @foreach($landingTemplates as $landingTemplate)
                <option value="{{ $landingTemplate->id }}" @selected((int) old('template_id', $clinic->theme?->template_id) === $landingTemplate->id)>
                    {{ $landingTemplate->name }}{{ $landingTemplate->is_default ? ' — '.__('landing_templates.fields.default') : '' }}
                </option>
            @endforeach
        </select>
        <span class="text-xs font-normal leading-6 text-zinc-600">{{ $clinic->theme ? __('clinics.landing_template_hint') : __('clinics.default_landing_template_hint') }}</span>
    </label>
@endif

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.domain') }}</span>
    <input name="domain" value="{{ old('domain', $primaryDomain?->domain) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.domain_type') }}</span>
    <select name="domain_type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
        @foreach(\App\Support\StateCatalog::options('tenant_domains.types') as $type => $label)
            <option value="{{ $type }}" @selected(old('domain_type', $primaryDomain?->type ?? 'tenant_subdomain') === $type)>{{ $label }}</option>
        @endforeach
    </select>
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.domain_status') }}</span>
    <select name="domain_status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
        @foreach(\App\Support\StateCatalog::options('tenant_domains.statuses') as $status => $label)
            <option value="{{ $status }}" @selected(old('domain_status', $primaryDomain?->status ?? 'active') === $status)>{{ $label }}</option>
        @endforeach
    </select>
</label>
