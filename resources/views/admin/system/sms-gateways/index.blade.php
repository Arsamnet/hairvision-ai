@extends('admin.layout')

@section('title', __('system.sms_gateways_title'))
@section('page_title', __('system.sms_gateways_title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('system.sms_gateways_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('sms.system_hint') }}</p>
            </div>
            @can('create', App\Models\SmsGateway::class)
                <x-admin.modal id="create-system-sms-gateway-modal" :label="__('system.new_gateway')" :title="__('system.new_gateway')" icon="message" :open="$errors->any() && old('_form') === 'create_system_sms_gateway'">
                    <form method="POST" action="{{ route('admin.system.sms-gateways.store') }}">
                        @csrf
                        @include('admin.system.sms-gateways.form', ['gateway' => null, 'formKey' => 'create_system_sms_gateway', 'returnToIndex' => true])
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <x-admin.list-filters
            preset-key="admin.system.sms-gateways.index"
            :search-placeholder="__('sms.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('sms.all_statuses'), 'options' => collect(['enabled', 'disabled'])->mapWithKeys(fn ($status) => [$status => __('system.statuses.'.$status)])->all()],
                ['name' => 'owner_scope', 'label' => __('system.owner'), 'type' => 'select', 'placeholder' => __('sms.all_owners'), 'options' => collect(['system', 'user'])->mapWithKeys(fn ($owner) => [$owner => __('system.owners.'.$owner)])->all()],
                ['name' => 'provider_code', 'label' => __('system.provider'), 'placeholder' => __('system.provider'), 'dir' => 'ltr'],
            ]"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'name' => __('system.name'),
                'provider_code' => __('system.provider'),
                'owner_scope' => __('system.owner'),
                'status' => __('system.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-[46rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('system.name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="provider_code" :label="__('system.provider')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="owner_scope" :label="__('system.owner')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('system.status')" /></th>
                        <th class="px-4 py-3">{{ __('system.default') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($gateways as $gateway)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('view', $gateway)
                                        <x-admin.table.action-item :href="route('admin.system.sms-gateways.show', $gateway)" icon="eye">{{ __('sms.details') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('update', $gateway)
                                        <x-admin.table.action-item :href="route('admin.system.sms-gateways.edit', $gateway)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('delete', $gateway)
                                        <x-admin.table.action-item :action="route('admin.system.sms-gateways.destroy', $gateway)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('sms.delete') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold">{{ $gateway->name }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $gateway->provider_code }}</td>
                            <td class="px-4 py-3">{{ $gateway->owner_scope === 'user' ? $gateway->user?->name : __('system.owners.system') }}</td>
                            <td class="px-4 py-3"><x-admin.badge :tone="$gateway->status === 'enabled' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$gateway->status) }}</x-admin.badge></td>
                            <td class="px-4 py-3">{{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="message">{{ __('sms.empty_gateways') }}</x-admin.empty-state>
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
                            @can('view', $gateway)
                                <x-admin.table.action-item :href="route('admin.system.sms-gateways.show', $gateway)" icon="eye">{{ __('sms.details') }}</x-admin.table.action-item>
                            @endcan
                            @can('update', $gateway)
                                <x-admin.table.action-item :href="route('admin.system.sms-gateways.edit', $gateway)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                            @endcan
                            @can('delete', $gateway)
                                <x-admin.table.action-item :action="route('admin.system.sms-gateways.destroy', $gateway)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('sms.delete') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('system.owner')">
                            <span class="truncate">{{ $gateway->owner_scope === 'user' ? $gateway->user?->name : __('system.owners.system') }}</span>
                        </x-admin.mobile-list.field>
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
                    <x-admin.empty-state icon="message">{{ __('sms.empty_gateways') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>
        <x-admin.table.pagination-footer :paginator="$gateways" />
    </x-admin.card>
@endsection
