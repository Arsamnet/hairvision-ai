@extends('admin.layout')

@section('title', __('system.payment_gateways_title'))
@section('page_title', __('system.payment_gateways_title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('system.payment_gateways_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('menu.items.payment_gateways') }}</p>
            </div>
            @can('permission', 'payment_gateways.create')
                <x-admin.modal id="create-payment-gateway-modal" :label="__('system.new_gateway')" :title="__('system.new_gateway')" icon="credit-card" :open="$errors->any() && old('_form') === 'create_payment_gateway'">
                    <form method="POST" action="{{ route('admin.system.payment-gateways.store') }}" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_form" value="create_payment_gateway">
                        <x-admin.validation-summary for="create_payment_gateway" class="md:col-span-2" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                        <input name="provider_code" value="local" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="{{ __('system.provider') }}">
                        <input name="merchant_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('payments.merchant_id') }}" dir="ltr">
                        <input name="callback_url" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('payments.callback_url') }}" dir="ltr">
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(['enabled', 'disabled'] as $status)
                                <option value="{{ $status }}">{{ __('system.statuses.'.$status) }}</option>
                            @endforeach
                        </select>
                        <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
                            <input type="checkbox" name="is_default" value="1">
                            <span>{{ __('system.default') }}</span>
                        </label>
                        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700 md:col-span-2">
                            <x-admin.icon name="credit-card" class="h-4 w-4" />
                            <span>{{ __('system.submit') }}</span>
                        </button>
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[1fr_10rem_10rem_8rem]">
            <input name="search" value="{{ request('search') }}" placeholder="{{ __('payments.search_placeholder') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
            <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                <option value="">{{ __('payments.all_statuses') }}</option>
                @foreach(['enabled', 'disabled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('system.statuses.'.$status) }}</option>
                @endforeach
            </select>
            <input name="provider_code" value="{{ request('provider_code') }}" placeholder="{{ __('payments.provider_placeholder') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500" dir="ltr">
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                <x-admin.icon name="settings" class="h-4 w-4" />
                <span>{{ __('payments.filter') }}</span>
            </button>
        </form>

        <div data-admin-table-container class="mt-5 overflow-visible rounded-lg border border-zinc-100">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3">{{ __('system.name') }}</th>
                        <th class="px-4 py-3">{{ __('system.provider') }}</th>
                        <th class="px-4 py-3">{{ __('system.status') }}</th>
                        <th class="px-4 py-3">{{ __('system.default') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($gateways as $gateway)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('permission', 'payment_gateways.edit')
                                        <x-admin.table.action-item
                                            :action="route('admin.system.payment-gateways.update', $gateway)"
                                            method="PUT"
                                            icon="edit"
                                            :inputs="array_filter([
                                                'name' => $gateway->name,
                                                'provider_code' => $gateway->provider_code,
                                                'status' => $gateway->status === 'enabled' ? 'disabled' : 'enabled',
                                                'is_default' => $gateway->is_default ? 1 : null,
                                            ], fn ($value) => $value !== null)"
                                        >
                                            {{ $gateway->status === 'enabled' ? __('system.statuses.disabled') : __('system.statuses.enabled') }}
                                        </x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold">{{ $gateway->name }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $gateway->provider_code }}</td>
                            <td class="px-4 py-3"><x-admin.badge :tone="$gateway->status === 'enabled' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$gateway->status) }}</x-admin.badge></td>
                            <td class="px-4 py-3">{{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12">
                                <x-admin.empty-state icon="credit-card">{{ __('payments.empty_gateways') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $gateways->links() }}</div>
    </x-admin.card>
@endsection
