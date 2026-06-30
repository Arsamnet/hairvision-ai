@extends('admin.layout')

@section('title', __('sms.dedicated_panel_title'))
@section('page_title', __('sms.dedicated_panel_title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('sms.dedicated_panel_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('sms.private_hint') }}</p>
            </div>
            <x-admin.badge :tone="($gateway?->status ?? 'disabled') === 'enabled' ? 'emerald' : 'zinc'">
                {{ __('system.statuses.'.($gateway?->status ?? 'disabled')) }}
            </x-admin.badge>
        </div>

        <form method="POST" action="{{ route('admin.sms-gateways.store') }}" class="mt-5">
            @csrf
            @include('admin.sms-gateways.form', ['gateway' => $gateway, 'formKey' => 'private_sms_gateway_settings'])
        </form>

        @if($gateway)
            <dl class="mt-6 grid gap-4 border-t border-zinc-100 pt-5 md:grid-cols-3">
                <div>
                    <dt class="text-sm text-zinc-500">{{ __('system.provider') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ $gateway->provider_code }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500">{{ __('sms.api_key') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ $gateway->maskedCredential('api_key') ?? __('messages.none') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-zinc-500">{{ __('sms.from_number') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ data_get($gateway->settings_json, 'from_number', data_get($gateway->settings_json, 'sender')) ?? __('messages.none') }}</dd>
                </div>
            </dl>
        @endif
    </x-admin.card>
@endsection
