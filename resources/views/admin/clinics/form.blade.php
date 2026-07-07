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
        @foreach(['active', 'inactive'] as $status)
            <option value="{{ $status }}" @selected(old('status', $clinic?->status ?? 'active') === $status)>{{ __('clinics.statuses.'.$status) }}</option>
        @endforeach
    </select>
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.domain') }}</span>
    <input name="domain" value="{{ old('domain', $primaryDomain?->domain) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.domain_type') }}</span>
    <select name="domain_type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
        @foreach(['tenant_subdomain', 'custom_domain', 'primary_domain', 'platform_subdomain'] as $type)
            <option value="{{ $type }}" @selected(old('domain_type', $primaryDomain?->type ?? 'tenant_subdomain') === $type)>{{ __('clinics.domain_types.'.$type) }}</option>
        @endforeach
    </select>
</label>

<label class="grid gap-1 text-sm font-bold">
    <span>{{ __('clinics.fields.domain_status') }}</span>
    <select name="domain_status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
        @foreach(['active', 'inactive', 'pending'] as $status)
            <option value="{{ $status }}" @selected(old('domain_status', $primaryDomain?->status ?? 'active') === $status)>{{ __('clinics.statuses.'.$status) }}</option>
        @endforeach
    </select>
</label>
