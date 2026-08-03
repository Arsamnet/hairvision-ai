{{-- @since 2.1.0 Adds age ranges, unified statuses, and lead deletion actions. --}}
@extends('admin.layout')

@section('title', __('leads.title'))
@section('page_title', __('leads.title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('leads.title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $clinicFilter?->name ?? __('dashboard.all_clinics') }}</p>
            </div>
            @can('create', App\Models\Lead::class)
                @if($tenant)
                    <x-admin.modal id="create-lead-modal" :label="__('leads.new_lead')" :title="__('leads.create_title')" icon="contact" :open="$errors->any() && old('_form') === 'create_lead'">
                        <form method="POST" action="{{ route('admin.leads.store') }}" class="grid gap-4 md:grid-cols-2">
                            @csrf
                            @include('admin.leads.form', ['formKey' => 'create_lead', 'returnToIndex' => true])
                        </form>
                    </x-admin.modal>
                @endif
            @endcan
        </div>

        @php
            $leadFilters = [
                ['name' => 'status', 'label' => __('leads.fields.status'), 'type' => 'select', 'placeholder' => __('leads.all_statuses'), 'options' => \App\Support\StateCatalog::options('leads.statuses')],
                ['name' => 'age_from', 'label' => __('leads.filters.age_from'), 'type' => 'number', 'placeholder' => __('leads.filters.age_from'), 'dir' => 'ltr', 'maxlength' => 3],
                ['name' => 'age_until', 'label' => __('leads.filters.age_until'), 'type' => 'number', 'placeholder' => __('leads.filters.age_until'), 'dir' => 'ltr', 'maxlength' => 3],
            ];

            if (($availableTenants?->count() ?? 0) > 1) {
                $leadFilters[] = ['name' => 'clinic_id', 'label' => __('system.clinic'), 'type' => 'select', 'placeholder' => __('dashboard.all_clinics'), 'options' => $availableTenants->pluck('name', 'id')->all()];
            }
        @endphp

        <x-admin.list-filters
            preset-key="admin.leads.index"
            :search-placeholder="__('leads.search_placeholder')"
            :filters="$leadFilters"
            :primary-filter-count="3"
            :sorts="[
                'created_at' => __('leads.fields.created_at'),
                'full_name' => __('leads.fields.name'),
                'phone_normalized' => __('leads.fields.phone'),
                'age' => __('leads.fields.age'),
                'status' => __('leads.fields.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-x-auto overflow-y-hidden rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-148 text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3 font-bold">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="full_name" :label="__('leads.fields.name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="phone_normalized" :label="__('leads.fields.phone')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="age" :label="__('leads.fields.age')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('leads.fields.status')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="created_at" :label="__('leads.fields.created_at')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($leads as $lead)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('view', $lead)
                                        <x-admin.table.action-item :href="route('admin.leads.show', $lead)" icon="eye">{{ __('leads.details') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('update', $lead)
                                        <x-admin.table.action-item :href="route('admin.leads.edit', $lead)" icon="edit">{{ __('leads.edit') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('delete', $lead)
                                        <x-admin.table.action-item :action="route('admin.leads.destroy', $lead)" method="DELETE" icon="trash" danger :confirm="__('leads.confirm_delete')">{{ __('leads.delete') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold">{{ $lead->full_name ?: __('leads.no_name') }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $lead->age ?? __('messages.none') }}</td>
                            <td class="px-4 py-3">
                                <x-admin.lead-status-badge :status="$lead->status" />
                            </td>
                            <td class="px-4 py-3 text-zinc-500"><x-jalali-date-time :value="$lead->created_at" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="contact">{{ __('leads.empty_filtered') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($leads as $lead)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $lead->full_name ?: __('leads.no_name') }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $lead->phone_normalized }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('view', $lead)
                                <x-admin.table.action-item :href="route('admin.leads.show', $lead)" icon="eye">{{ __('leads.details') }}</x-admin.table.action-item>
                            @endcan
                            @can('update', $lead)
                                <x-admin.table.action-item :href="route('admin.leads.edit', $lead)" icon="edit">{{ __('leads.edit') }}</x-admin.table.action-item>
                            @endcan
                            @can('delete', $lead)
                                <x-admin.table.action-item :action="route('admin.leads.destroy', $lead)" method="DELETE" icon="trash" danger :confirm="__('leads.confirm_delete')">{{ __('leads.delete') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('leads.fields.status')">
                            <x-admin.lead-status-badge :status="$lead->status" />
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('leads.fields.age')">
                            {{ $lead->age ?? __('messages.none') }}
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('leads.fields.created_at')">
                            <x-jalali-date-time :value="$lead->created_at" />
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="contact">{{ __('leads.empty_filtered') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$leads" />
    </x-admin.card>
@endsection
