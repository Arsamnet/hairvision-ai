@extends('admin.layout')

@section('title', __('system.payment_gateways_title'))
@section('page_title', __('system.payment_gateways_title'))

@section('content')
    @can('permission', 'payment_gateways.create')
        <section class="mb-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('system.new_gateway') }}</h2>
            <form method="POST" action="{{ route('admin.system.payment-gateways.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <input name="name" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="{{ __('system.name') }}">
                <input name="provider_code" value="local" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" dir="ltr">
                <input name="merchant_id" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="merchant" dir="ltr">
                <input name="callback_url" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="callback" dir="ltr">
                <select name="status" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    @foreach(['enabled', 'disabled'] as $status)
                        <option value="{{ $status }}">{{ __('system.statuses.'.$status) }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_default" value="1"> {{ __('system.default') }}</label>
                <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white">{{ __('system.submit') }}</button>
            </form>
        </section>
    @endcan

    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3">{{ __('system.name') }}</th>
                        <th class="py-3">{{ __('system.provider') }}</th>
                        <th class="py-3">{{ __('system.status') }}</th>
                        <th class="py-3">{{ __('system.default') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($gateways as $gateway)
                        <tr>
                            <td class="py-3 font-bold">{{ $gateway->name }}</td>
                            <td class="py-3" dir="ltr">{{ $gateway->provider_code }}</td>
                            <td class="py-3">{{ __('system.statuses.'.$gateway->status) }}</td>
                            <td class="py-3">{{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $gateways->links() }}</div>
    </section>
@endsection
