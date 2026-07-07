@extends('admin.layout')

@section('title', __('clinics.title'))
@section('page_title', __('clinics.title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('clinics.manage') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('clinics.subtitle') }}</p>
            </div>
            @can('create', App\Models\Tenant::class)
                <x-admin.modal id="create-clinic-modal" :label="__('clinics.create')" :title="__('clinics.create')" icon="building" :open="$errors->any() && old('_form') === 'create_clinic'">
                    <form method="POST" action="{{ route('admin.clinics.store') }}" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        @include('admin.clinics.form', [
                            'clinic' => null,
                            'primaryDomain' => null,
                            'formKey' => 'create_clinic',
                        ])
                        <x-admin.button type="submit" icon="building" class="md:col-span-2">
                            <span>{{ __('system.submit') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <x-admin.list-filters
            preset-key="admin.clinics.index"
            :search-placeholder="__('clinics.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('clinics.fields.status'), 'type' => 'select', 'placeholder' => __('clinics.all_statuses'), 'options' => ['active' => __('clinics.statuses.active'), 'inactive' => __('clinics.statuses.inactive')]],
            ]"
            :sorts="[
                'created_at' => __('clinics.fields.created_at'),
                'name' => __('clinics.fields.name'),
                'slug' => __('clinics.fields.slug'),
                'status' => __('clinics.fields.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-216 text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('clinics.fields.name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="slug" :label="__('clinics.fields.slug')" /></th>
                        <th class="px-4 py-3">{{ __('clinics.primary_domain') }}</th>
                        <th class="px-4 py-3">{{ __('clinics.plan_status') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('clinics.fields.status')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($clinics as $clinic)
                        @php
                            $primaryDomain = $clinic->domains->first();
                            $subscription = $clinic->subscriptions->first();
                            $remainingCredit = $subscription?->serviceEntitlements->sum(fn ($entitlement) => $entitlement->remainingCredit()) ?? 0;
                        @endphp
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('view', $clinic)
                                        <x-admin.table.action-item :href="route('admin.clinics.show', $clinic)" icon="eye">{{ __('clinics.view') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('update', $clinic)
                                        <x-admin.table.action-item :href="route('admin.clinics.edit', $clinic)" icon="edit">{{ __('clinics.edit') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('delete', $clinic)
                                        <x-admin.table.action-item :action="route('admin.clinics.destroy', $clinic)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold">{{ $clinic->name }}</td>
                            <td class="px-4 py-3 text-zinc-500" dir="ltr">{{ $clinic->slug }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $primaryDomain?->domain ?? __('messages.none') }}</td>
                            <td class="px-4 py-3">
                                @if($subscription)
                                    <div class="space-y-2">
                                        <p class="font-bold text-zinc-950">{{ $subscription->plan?->name ?? __('messages.none') }}</p>
                                        <p class="text-xs font-bold text-zinc-500">{{ __('subscriptions.remaining_credit') }}: <span dir="ltr">{{ number_format($remainingCredit) }}</span></p>
                                    </div>
                                @else
                                    <x-admin.badge tone="amber">{{ __('clinics.no_active_plan') }}</x-admin.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-admin.badge :tone="$clinic->status === 'active' ? 'emerald' : 'zinc'">{{ __('clinics.statuses.'.$clinic->status) }}</x-admin.badge></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="building">{{ __('clinics.empty') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($clinics as $clinic)
                @php
                    $primaryDomain = $clinic->domains->first();
                    $subscription = $clinic->subscriptions->first();
                    $remainingCredit = $subscription?->serviceEntitlements->sum(fn ($entitlement) => $entitlement->remainingCredit()) ?? 0;
                @endphp
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $clinic->name }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $primaryDomain?->domain ?? $clinic->slug }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('view', $clinic)
                                <x-admin.table.action-item :href="route('admin.clinics.show', $clinic)" icon="eye">{{ __('clinics.view') }}</x-admin.table.action-item>
                            @endcan
                            @can('update', $clinic)
                                <x-admin.table.action-item :href="route('admin.clinics.edit', $clinic)" icon="edit">{{ __('clinics.edit') }}</x-admin.table.action-item>
                            @endcan
                            @can('delete', $clinic)
                                <x-admin.table.action-item :action="route('admin.clinics.destroy', $clinic)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('clinics.fields.status')">
                            <x-admin.badge :tone="$clinic->status === 'active' ? 'emerald' : 'zinc'">{{ __('clinics.statuses.'.$clinic->status) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('clinics.fields.slug')">
                            <span dir="ltr">{{ $clinic->slug }}</span>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('clinics.plan_status')">
                            @if($subscription)
                                <span class="space-y-1">
                                    <span class="block font-bold">{{ $subscription->plan?->name ?? __('messages.none') }}</span>
                                    <span class="block text-xs text-zinc-500">
                                        {{ __('subscriptions.remaining_credit') }}:
                                        <span dir="ltr">{{ number_format($remainingCredit) }}</span>
                                    </span>
                                </span>
                            @else
                                <x-admin.badge tone="amber">{{ __('clinics.no_active_plan') }}</x-admin.badge>
                            @endif
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="building">{{ __('clinics.empty') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$clinics" />
    </x-admin.card>
@endsection
