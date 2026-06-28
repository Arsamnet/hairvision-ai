@php
    $formKey = $formKey ?? ($gateway ? 'edit_private_sms_gateway' : 'create_private_sms_gateway');
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
        <input name="provider_code" value="{{ old('provider_code', $gateway?->provider_code ?? 'local') }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.api_key') }}</span>
        <input name="api_key" placeholder="{{ $gateway?->maskedCredential('api_key') ?? __('sms.api_key_placeholder') }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('sms.sender') }}</span>
        <input name="sender" value="{{ old('sender', data_get($gateway?->settings_json, 'sender')) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
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
    <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-5 text-sm font-bold text-white transition hover:bg-teal-700">
        <x-admin.icon name="message" class="h-4 w-4" />
        <span>{{ __('system.save') }}</span>
    </button>
    <a href="{{ route('admin.sms-gateways.index') }}" class="rounded-lg border border-zinc-200 px-4 py-2.5 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700">{{ __('messages.cancel') }}</a>
</div>
