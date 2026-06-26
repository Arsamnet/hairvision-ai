@extends('admin.layout')

@section('title', __('dashboard.admin_title'))
@section('page_title', __('dashboard.dashboard'))

@section('content')
    @php
        $planStatus = $planAccess['status']->value ?? 'inactive';
        $planMessage = __('dashboard.plan.'.$planStatus);
        $remainingCredit = $activeSubscription ? max(0, $activeSubscription->usage_limit - $activeSubscription->usage_used) : 0;
    @endphp

    <section class="mb-5 overflow-hidden rounded-lg border border-teal-100 bg-white shadow-sm">
        <div class="grid gap-0 lg:grid-cols-[1.3fr_.7fr]">
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700">
                        <x-admin.icon name="sparkles" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold">{{ __('dashboard.plan.status_title') }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-zinc-600">{{ $planMessage }}</p>
                    </div>
                </div>

                @if(! in_array($planStatus, ['active', 'exempt'], true))
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('admin.plans.index') }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white transition hover:bg-teal-700">{{ __('dashboard.plan.view_plans') }}</a>
                        <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700">{{ __('dashboard.plan.renew_plan') }}</a>
                        <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700">{{ __('dashboard.plan.upgrade_plan') }}</a>
                    </div>
                @endif
            </div>

            <div class="border-t border-teal-100 bg-linear-to-br from-teal-50 via-white to-amber-50 p-5 sm:p-6 lg:border-r lg:border-t-0">
                @if($activeSubscription)
                    <p class="text-sm font-bold text-zinc-500">{{ $activeSubscription->plan?->name }}</p>
                    <p class="mt-3 text-2xl font-bold text-zinc-950">{{ __('dashboard.plan.remaining_credit', ['count' => number_format($remainingCredit)]) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ __('dashboard.plan.active_until', ['date' => $activeSubscription->ends_at?->toDateString()]) }}</p>
                @else
                    <p class="text-sm font-bold text-zinc-500">{{ $tenant?->name ?? __('dashboard.platform_overview') }}</p>
                    <p class="mt-3 text-2xl font-bold text-zinc-950">{{ __('dashboard.plan.'.($planStatus === 'exempt' ? 'exempt' : 'status_title')) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ $tenant?->name ?? __('dashboard.all_clinics') }}</p>
                @endif
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => __('dashboard.cards.total_leads'), 'value' => $leadCount, 'icon' => 'contact', 'tone' => 'bg-zinc-950 text-white ring-zinc-950'],
            ['label' => __('dashboard.cards.new_leads'), 'value' => $newLeadCount, 'icon' => 'sparkles', 'tone' => 'bg-white text-zinc-950 ring-zinc-200'],
            ['label' => __('dashboard.cards.follow_up'), 'value' => $followUpCount, 'icon' => 'message', 'tone' => 'bg-white text-zinc-950 ring-zinc-200'],
            ['label' => __('dashboard.cards.converted'), 'value' => $convertedCount, 'icon' => 'chart', 'tone' => 'bg-white text-zinc-950 ring-zinc-200'],
        ] as $card)
            <section class="rounded-lg {{ $card['tone'] }} p-5 shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold opacity-70">{{ $card['label'] }}</p>
                    <x-admin.icon :name="$card['icon']" class="h-5 w-5 opacity-70" />
                </div>
                <p class="mt-4 text-3xl font-bold">{{ number_format($card['value']) }}</p>
            </section>
        @endforeach
    </div>

    <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('dashboard.recent_leads') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? __('dashboard.platform_overview') }}</p>
            </div>
            @can('permission', 'leads.list')
                <a href="{{ route('admin.leads.index') }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white transition hover:bg-teal-700">{{ __('dashboard.view_all') }}</a>
            @endcan
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3 font-bold">{{ __('leads.fields.name') }}</th>
                        <th class="py-3 font-bold">{{ __('leads.fields.phone') }}</th>
                        <th class="py-3 font-bold">{{ __('leads.fields.crm_stage') }}</th>
                        <th class="py-3 font-bold">{{ __('leads.fields.tenant') }}</th>
                        <th class="py-3 font-bold">{{ __('leads.fields.registered_at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($recentLeads as $lead)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="py-3"><a class="font-bold text-zinc-950 hover:text-teal-700" href="{{ route('admin.leads.show', $lead) }}">{{ $lead->full_name ?: __('dashboard.no_name') }}</a></td>
                            <td class="py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="py-3">
                                <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">{{ __('leads.crm_stages.'.$lead->crm_stage) }}</span>
                            </td>
                            <td class="py-3 text-zinc-600">{{ $lead->tenant?->name }}</td>
                            <td class="py-3 text-zinc-500">{{ $lead->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-zinc-500">{{ __('dashboard.empty_recent_leads') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
