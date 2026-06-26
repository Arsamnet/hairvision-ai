@extends('admin.layout')

@section('title', __('leads.title'))
@section('page_title', __('leads.title'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('leads.manage') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? __('dashboard.all_clinics') }}</p>
            </div>
            @can('create', App\Models\Lead::class)
                @if($tenant)
                    <a href="{{ route('admin.leads.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white transition hover:bg-teal-700">
                        <x-admin.icon name="contact" class="h-4 w-4" />
                        <span>{{ __('leads.new_lead') }}</span>
                    </a>
                @endif
            @endcan
        </div>

        <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[1fr_11rem_13rem_9rem]">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('leads.search_placeholder') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
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
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                <x-admin.icon name="settings" class="h-4 w-4" />
                <span>{{ __('leads.filter') }}</span>
            </button>
        </form>

        <div class="mt-5 overflow-x-auto rounded-lg border border-zinc-100">
            <table class="w-full min-w-[58rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.name') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.phone') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.status') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.crm_stage') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.source') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.owner') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('leads.fields.created_at') }}</th>
                        <th class="px-4 py-3 font-bold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($leads as $lead)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 font-bold">{{ $lead->full_name ?: __('leads.no_name') }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-700">{{ __('leads.statuses.'.$lead->status) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">{{ __('leads.crm_stages.'.$lead->crm_stage) }}</span>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ $lead->source ?: __('messages.none') }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $lead->assignedUser?->name ?: __('messages.none') }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $lead->created_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.leads.show', $lead) }}" class="rounded-lg bg-zinc-100 px-3 py-2 text-xs font-bold text-zinc-800 transition hover:bg-teal-50 hover:text-teal-700">{{ __('leads.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="mx-auto max-w-sm">
                                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-lg bg-zinc-50 text-zinc-400">
                                        <x-admin.icon name="contact" />
                                    </span>
                                    <p class="mt-3 text-sm font-bold text-zinc-600">{{ __('leads.empty_filtered') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $leads->links() }}</div>
    </section>
@endsection
