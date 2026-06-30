@extends('admin.layout')

@section('title', __('sms.my_gateway_title'))
@section('page_title', __('sms.my_gateway_title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('sms.my_gateway_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('sms.private_hint') }}</p>
            </div>
            <x-admin.modal id="create-private-sms-gateway-modal" :label="__('system.new_private_gateway')" :title="__('system.new_private_gateway')" icon="message" :open="$errors->any() && old('_form') === 'create_private_sms_gateway'">
                <form method="POST" action="{{ route('admin.sms-gateways.store') }}">
                    @csrf
                    @include('admin.sms-gateways.form', ['gateway' => null, 'formKey' => 'create_private_sms_gateway', 'returnToIndex' => true])
                </form>
            </x-admin.modal>
        </div>

        <x-admin.list-filters
            preset-key="admin.sms-gateways.index"
            :search-placeholder="__('sms.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('sms.all_statuses'), 'options' => collect(['enabled', 'disabled'])->mapWithKeys(fn ($status) => [$status => __('system.statuses.'.$status)])->all()],
                ['name' => 'provider_code', 'label' => __('system.provider'), 'placeholder' => __('system.provider'), 'dir' => 'ltr'],
            ]"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'name' => __('system.name'),
                'provider_code' => __('system.provider'),
                'status' => __('system.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 overflow-visible rounded-lg border border-zinc-100">
            <table class="w-full min-w-[42rem] text-right text-sm">
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
                                    <x-admin.table.action-item :href="route('admin.sms-gateways.show', $gateway)" icon="eye">{{ __('sms.details') }}</x-admin.table.action-item>
                                    <x-admin.table.action-item :href="route('admin.sms-gateways.edit', $gateway)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                                    <x-admin.table.action-item :action="route('admin.sms-gateways.destroy', $gateway)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('sms.delete') }}</x-admin.table.action-item>
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
                                <x-admin.empty-state icon="message">{{ __('sms.empty_private_gateways') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table.pagination-footer :paginator="$gateways" />
    </x-admin.card>
@endsection
