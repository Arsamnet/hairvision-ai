@php
    $formKey = $formKey ?? ($gateway ? 'edit_clinic_sms_gateway' : 'create_clinic_sms_gateway');
    $providerOptions = $providerOptions ?? ['local' => __('sms.providers.local'), 'ippanel' => __('sms.providers.ippanel')];
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
@if($returnToIndex ?? false)
    <input type="hidden" name="return_to_index" value="1">
@endif

<x-admin.validation-summary :for="$formKey" class="mb-4" />

<div class="grid gap-4 md:grid-cols-2">
    <input type="hidden" name="name" value="{{ old('name', $gateway?->name ?? __('sms.clinic_gateway_name')) }}">

    <label class="block md:col-span-2">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.provider') }}</span>
        <select name="provider_code" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach($providerOptions as $providerCode => $providerLabel)
                <option value="{{ $providerCode }}" @selected(old('provider_code', $gateway?->provider_code ?? array_key_first($providerOptions)) === $providerCode)>{{ $providerLabel }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.api_key') }}</span>
        <input name="api_key" placeholder="{{ $gateway?->maskedCredential('api_key') ?? __('sms.api_key_placeholder') }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.from_number') }}</span>
        <input name="from_number" value="{{ old('from_number', data_get($gateway?->settings_json, 'from_number', data_get($gateway?->settings_json, 'sender'))) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="flex min-h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', ($gateway?->status ?? 'disabled') === 'enabled'))>
        <span>{{ __('sms.activate_clinic_gateway') }}</span>
    </label>
</div>

<div class="mt-6 flex flex-wrap items-center gap-2">
    <x-admin.button type="submit" size="md-wide" icon="message">
        <span>{{ __('system.save') }}</span>
    </x-admin.button>
</div>
