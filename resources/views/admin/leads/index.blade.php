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

        <form method="GET" class="mt-5 grid gap-3 lg:grid-cols-[1fr_11rem_13rem_13rem_9rem]">
            <input name="search" value="{{ request('search', request('q')) }}" placeholder="{{ __('leads.search_placeholder') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
            <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                <option value="">{{ __('leads.all_statuses') }}</option>
                @foreach(__('leads.statuses') as $status => $label)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="crm_stage" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                <option value="">{{ __('leads.all_stages') }}</option>
                @foreach(__('leads.crm_stages') as $stage => $label)
                    <option value="{{ $stage }}" @selected(request('crm_stage') === $stage)>{{ $label }}</option>
                @endforeach
            </select>
            @if(($availableTenants?->count() ?? 0) > 1)
                <select name="clinic_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                    <option value="">{{ __('dashboard.all_clinics') }}</option>
                    @foreach($availableTenants as $tenantOption)
                        <option value="{{ $tenantOption->id }}" @selected((string) request('clinic_id') === (string) $tenantOption->id)>{{ $tenantOption->name }}</option>
                    @endforeach
                </select>
            @else
                <div></div>
            @endif
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                <x-admin.icon name="settings" class="h-4 w-4" />
                <span>{{ __('leads.filter') }}</span>
            </button>
        </form>

        <div class="mt-5 overflow-x-auto rounded-lg border border-zinc-100">
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
