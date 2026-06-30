@extends('admin.layout')

@section('title', __('leads.title'))
@section('page_title', __('leads.title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('leads.manage') }}</h2>
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
                ['name' => 'status', 'label' => __('leads.fields.status'), 'type' => 'select', 'placeholder' => __('leads.all_statuses'), 'options' => __('leads.statuses')],
                ['name' => 'crm_stage', 'label' => __('leads.fields.crm_stage'), 'type' => 'select', 'placeholder' => __('leads.all_stages'), 'options' => __('leads.crm_stages')],
            ];

            if (($availableTenants?->count() ?? 0) > 1) {
                $leadFilters[] = ['name' => 'clinic_id', 'label' => __('system.clinic'), 'type' => 'select', 'placeholder' => __('dashboard.all_clinics'), 'options' => $availableTenants->pluck('name', 'id')->all()];
            }
        @endphp

        <x-admin.list-filters
            preset-key="admin.leads.index"
            :search-placeholder="__('leads.search_placeholder')"
            :filters="$leadFilters"
            :sorts="[
                'created_at' => __('leads.fields.created_at'),
                'full_name' => __('leads.fields.name'),
                'phone_normalized' => __('leads.fields.phone'),
                'crm_stage' => __('leads.fields.crm_stage'),
                'status' => __('leads.fields.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 overflow-visible rounded-lg border border-zinc-100">
            <table class="w-full min-w-[46rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3 font-bold">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.name') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.phone') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.status') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.crm_stage') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.created_at') }}</th>
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
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold">{{ $lead->full_name ?: __('leads.no_name') }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge>{{ __('leads.statuses.'.$lead->status) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge tone="teal">{{ __('leads.crm_stages.'.$lead->crm_stage) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500">{{ $lead->created_at?->diffForHumans() }}</td>
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

        <div class="mt-5">{{ $leads->links() }}</div>
    </x-admin.card>
@endsection
