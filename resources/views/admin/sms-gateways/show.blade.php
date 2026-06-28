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
                <a href="{{ route('admin.sms-gateways.edit', $gateway) }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white transition hover:bg-teal-700">{{ __('system.edit') }}</a>
                <form method="POST" action="{{ route('admin.sms-gateways.destroy', $gateway) }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-50">{{ __('sms.delete') }}</button>
                </form>
            </div>
        </div>

        <dl class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.status') }}</dt><dd class="mt-1 font-bold">{{ __('system.statuses.'.$gateway->status) }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.default') }}</dt><dd class="mt-1 font-bold">{{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('sms.api_key') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ $gateway->maskedCredential('api_key') ?? __('messages.none') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('sms.sender') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ data_get($gateway->settings_json, 'sender') ?? __('messages.none') }}</dd></div>
        </dl>
    </section>
@endsection
