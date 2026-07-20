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
                    <form method="POST" action="{{ route('admin.system.payment-gateways.store') }}" class="grid gap-3 lg:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_form" value="create_payment_gateway">
                        <x-admin.validation-summary for="create_payment_gateway" class="lg:col-span-2" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                        <input name="provider_code" value="local" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="{{ __('system.provider') }}">
                        <input type="password" name="merchant_id" autocomplete="new-password" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('payments.merchant_id') }}" dir="ltr">
                        <div class="rounded-lg border border-teal-100 bg-teal-50 px-4 py-3 text-sm lg:col-span-2">
                            <p class="font-bold text-teal-900">{{ __('payments.callback_url_automatic') }}</p>
                            <p class="mt-1 leading-6 text-teal-700">{{ __('payments.callback_url_automatic_hint') }}</p>
                        </div>
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(\App\Support\StateCatalog::options('payment_gateways.statuses') as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
                            <input type="checkbox" name="is_default" value="1">
                            <span>{{ __('system.default') }}</span>
                        </label>
                        <x-admin.button type="submit" icon="credit-card" class="lg:col-span-2">
                            <span>{{ __('system.submit') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <x-admin.list-filters
            preset-key="admin.system.payment-gateways.index"
            :search-placeholder="__('payments.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('payments.all_statuses'), 'options' => \App\Support\StateCatalog::options('payment_gateways.statuses')],
                ['name' => 'provider_code', 'label' => __('system.provider'), 'placeholder' => __('payments.provider_placeholder'), 'dir' => 'ltr'],
            ]"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'name' => __('system.name'),
                'provider_code' => __('system.provider'),
                'status' => __('system.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-x-auto overflow-y-hidden rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-2xl text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('system.name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="provider_code" :label="__('system.provider')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('system.status')" /></th>
                        <th class="px-4 py-3">{{ __('system.default') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($gateways as $gateway)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('permission', 'payment_gateways.edit')
                                        <label for="edit-payment-gateway-{{ $gateway->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="edit-payment-gateway-{{ $gateway->id }}">
                                            <x-admin.icon name="edit" class="h-4 w-4" />
                                            <span>{{ __('system.edit') }}</span>
                                        </label>
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

        <x-admin.mobile-list>
            @forelse($gateways as $gateway)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $gateway->name }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $gateway->provider_code }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('permission', 'payment_gateways.edit')
                                <label for="edit-payment-gateway-{{ $gateway->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="edit-payment-gateway-{{ $gateway->id }}">
                                    <x-admin.icon name="edit" class="h-4 w-4" />
                                    <span>{{ __('system.edit') }}</span>
                                </label>
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
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('system.status')">
                            <x-admin.badge :tone="$gateway->status === 'enabled' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$gateway->status) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('system.default')">
                            {{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="credit-card">{{ __('payments.empty_gateways') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>
        <x-admin.table.pagination-footer :paginator="$gateways" />

        @can('permission', 'payment_gateways.edit')
            @foreach($gateways as $gateway)
                @php($editForm = 'edit_payment_gateway_'.$gateway->id)
                @php($isCurrentEditForm = old('_form') === $editForm)
                <x-admin.modal id="edit-payment-gateway-{{ $gateway->id }}" :label="__('system.edit')" :title="$gateway->name" icon="edit" :trigger="false" :open="$errors->any() && $isCurrentEditForm">
                    <form method="POST" action="{{ route('admin.system.payment-gateways.update', $gateway) }}" class="grid gap-3 lg:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="{{ $editForm }}">
                        <x-admin.validation-summary :for="$editForm" class="lg:col-span-2" />
                        <input name="name" value="{{ $isCurrentEditForm ? old('name') : $gateway->name }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                        <input name="provider_code" value="{{ $gateway->provider_code }}" readonly class="h-11 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-500 outline-none" dir="ltr" placeholder="{{ __('system.provider') }}">
                        <div>
                            <input type="password" name="merchant_id" autocomplete="new-password" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('payments.merchant_id') }}" dir="ltr">
                            <p class="mt-1 text-xs text-zinc-500">{{ __('payments.merchant_id_preserved') }}</p>
                        </div>
                        @if($gateway->provider_code === 'zarinpal')
                            <div class="rounded-lg border border-teal-100 bg-teal-50 px-4 py-3 text-sm lg:col-span-2" data-automatic-zarinpal-callback>
                                <p class="font-bold text-teal-900">{{ __('payments.callback_url_automatic') }}</p>
                                <code class="mt-2 block overflow-x-auto rounded-lg bg-white px-3 py-2 text-left text-xs text-zinc-700" dir="ltr">{{ url('/admin/billing/payments/{payment}/verify') }}</code>
                                <p class="mt-2 leading-6 text-teal-700">{{ __('payments.callback_url_automatic_hint') }}</p>
                            </div>
                        @else
                            <input name="callback_url" value="{{ $isCurrentEditForm ? old('callback_url') : data_get($gateway->settings_json, 'callback_url') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('payments.callback_url') }}" dir="ltr">
                        @endif
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(\App\Support\StateCatalog::options('payment_gateways.statuses') as $status => $label)
                                <option value="{{ $status }}" @selected(($isCurrentEditForm ? old('status') : $gateway->status) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
                            <input type="checkbox" name="is_default" value="1" @checked($isCurrentEditForm ? old('is_default') : $gateway->is_default)>
                            <span>{{ __('system.default') }}</span>
                        </label>
                        <x-admin.button type="submit" icon="check" class="lg:col-span-2">
                            <span>{{ __('system.save') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endforeach
        @endcan
    </x-admin.card>
@endsection
