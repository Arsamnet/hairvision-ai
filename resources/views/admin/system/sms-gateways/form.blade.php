@php
    $formKey = $formKey ?? ($gateway ? 'edit_system_sms_gateway' : 'create_system_sms_gateway');
    $providerOptions = $providerOptions ?? ['local' => __('sms.providers.local'), 'ippanel' => __('sms.providers.ippanel')];
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
@if($returnToIndex ?? false)
    <input type="hidden" name="return_to_index" value="1">
@endif

<x-admin.validation-summary :for="$formKey" class="mb-4" />

<div class="grid gap-4 md:grid-cols-2">
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.name') }}</span>
        <input name="name" value="{{ old('name', $gateway?->name) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.provider') }}</span>
        <select name="provider_code" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach($providerOptions as $providerCode => $providerLabel)
                <option value="{{ $providerCode }}" @selected(old('provider_code', $gateway?->provider_code ?? 'ippanel') === $providerCode)>{{ $providerLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.owner') }}</span>
        <select name="owner_scope" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach(['system', 'user'] as $owner)
                <option value="{{ $owner }}" @selected(old('owner_scope', $gateway?->owner_scope ?? 'system') === $owner)>{{ __('system.owners.'.$owner) }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.private_user') }}</span>
        <select name="user_id" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            <option value="">{{ __('system.no_private_user') }}</option>
            @foreach($users as $userOption)
                <option value="{{ $userOption->id }}" @selected((string) old('user_id', $gateway?->user_id) === (string) $userOption->id)>{{ $userOption->name }}</option>
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
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.otp_pattern_code') }}</span>
        <input name="otp_pattern_code" value="{{ old('otp_pattern_code', data_get($gateway?->settings_json, 'otp_pattern_code')) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.otp_pattern_variable') }}</span>
        <input name="otp_pattern_variable" value="{{ old('otp_pattern_variable', data_get($gateway?->settings_json, 'otp_pattern_variable', config('hairvision.sms.ippanel.otp_pattern_variable'))) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.endpoint_url') }}</span>
        <input name="endpoint_url" value="{{ old('endpoint_url', data_get($gateway?->settings_json, 'endpoint_url')) }}" placeholder="https://edge.ippanel.com/v1/api/send" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.status') }}</span>
        <select name="status" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach(['enabled', 'disabled'] as $status)
                <option value="{{ $status }}" @selected(old('status', $gateway?->status ?? 'enabled') === $status)>{{ __('system.statuses.'.$status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="mt-7 flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $gateway?->is_default ?? false))>
        <span>{{ __('system.default') }}</span>
    </label>
</div>

<div class="mt-6 flex flex-wrap items-center gap-2">
    <x-admin.button type="submit" size="md-wide" icon="message">
        <span>{{ __('system.save') }}</span>
    </x-admin.button>
    <x-admin.button :href="route('admin.system.sms-gateways.index')" variant="secondary" size="compact-md">{{ __('messages.cancel') }}</x-admin.button>
</div>
