@extends('admin.layout')

@section('title', __('sms.details'))
@section('page_title', __('sms.details'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ $gateway->name }}</h2>
                <p class="mt-1 text-sm text-zinc-500" dir="ltr">{{ $gateway->provider_code }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $gateway)
                    <x-admin.button :href="route('admin.system.sms-gateways.edit', $gateway)" size="compact">{{ __('system.edit') }}</x-admin.button>
                @endcan
                @can('delete', $gateway)
                    <form method="POST" action="{{ route('admin.system.sms-gateways.destroy', $gateway) }}" data-confirm-form data-confirm-message="{{ __('messages.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-admin.button type="submit" variant="danger-outline" size="compact">{{ __('sms.delete') }}</x-admin.button>
                    </form>
                @endcan
            </div>
        </div>

        <dl class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.owner') }}</dt><dd class="mt-1 font-bold">{{ $gateway->owner_scope === 'user' ? $gateway->user?->name : __('system.owners.system') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.status') }}</dt><dd class="mt-1 font-bold">{{ __('system.statuses.'.$gateway->status) }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.default') }}</dt><dd class="mt-1 font-bold">{{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('sms.api_key') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ $gateway->maskedCredential('api_key') ?? __('messages.none') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('sms.from_number') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ data_get($gateway->settings_json, 'from_number', data_get($gateway->settings_json, 'sender')) ?? __('messages.none') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('sms.otp_pattern_code') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ data_get($gateway->settings_json, 'otp_pattern_code') ?? __('messages.none') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('sms.otp_pattern_variable') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ data_get($gateway->settings_json, 'otp_pattern_variable', config('hairvision.sms.ippanel.otp_pattern_variable')) ?? __('messages.none') }}</dd></div>
        </dl>
    </section>
@endsection
