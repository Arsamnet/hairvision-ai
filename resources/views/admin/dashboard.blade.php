@extends('admin.layout')

@section('title', __('dashboard.admin_title'))
@section('page_title', __('dashboard.dashboard'))

@section('content')
    @php
        $planStatus = $planAccess['status']->value ?? 'inactive';
        $planMessage = __('dashboard.plan.'.$planStatus);
        $remainingCredit = $activeSubscription ? $serviceSummaries->sum('remaining_credit') : 0;
    @endphp

    <section class="mb-5 overflow-hidden rounded-lg border border-teal-100 bg-white shadow-sm">
        <div class="grid gap-0 lg:grid-cols-[1.3fr_.7fr]">
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700">
                        <x-admin.icon name="package" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold">{{ __('dashboard.plan.status_title') }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-zinc-600">{{ $planMessage }}</p>
                    </div>
                </div>

                @if($planStatus !== 'exempt')
                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-admin.button :href="route('admin.plans.index')" size="sm" icon="package">
                            <span>{{ __('dashboard.plan.view_plans') }}</span>
                        </x-admin.button>
                        @if($activeSubscription)
                            <x-admin.button :href="route('admin.plans.index')" variant="secondary" size="sm" icon="credit-card">
                                <span>{{ __('dashboard.plan.upgrade_plan') }}</span>
                            </x-admin.button>
                        @else
                            <x-admin.button :href="route('admin.plans.index')" variant="secondary" size="sm" icon="credit-card">
                                <span>{{ __('dashboard.plan.renew_plan') }}</span>
                            </x-admin.button>
                        @endif
                    </div>
                @endif
            </div>

            <div class="border-t border-teal-100 p-5 sm:p-6 lg:border-r lg:border-t-0 bg-white dark:bg-zinc-950">
                @if($activeSubscription)
                    <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $activeSubscription->plan?->name }}</p>
                    <p class="mt-3 text-2xl font-bold text-zinc-950 dark:text-zinc-100">{{ __('dashboard.plan.remaining_credit', ['count' => number_format($remainingCredit)]) }}</p>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('dashboard.plan.active_until', ['date' => \App\Support\JalaliDate::toJalaliDate($activeSubscription->ends_at)]) }}</p>
                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">{{ __('dashboard.plan.service_credits') }}</p>
                        @forelse($serviceSummaries as $service)
                            <div class="rounded-lg bg-white/80 px-3 py-2 text-xs ring-1 ring-zinc-100 dark:bg-white/[0.04] dark:ring-white/10">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-zinc-800 dark:text-zinc-100">{{ $service['label'] }}</span>
                                    <span class="text-zinc-600 dark:text-zinc-300">{{ number_format($service['remaining_credit']) }} / {{ number_format($service['total_credit']) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800 dark:bg-amber-300/10 dark:text-amber-200 dark:ring-1 dark:ring-amber-300/15">{{ __('dashboard.plan.no_service_credit') }}</p>
                        @endforelse
                    </div>
                @else
                    <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $tenant?->name ?? __('dashboard.platform_overview') }}</p>
                    <p class="mt-3 text-2xl font-bold text-zinc-950 dark:text-zinc-100">{{ __('dashboard.plan.'.($planStatus === 'exempt' ? 'exempt' : 'status_title')) }}</p>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $tenant?->name ?? __('dashboard.all_clinics') }}</p>
                @endif
            </div>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
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
                <x-admin.button :href="route('admin.leads.index')" size="compact">{{ __('dashboard.view_all') }}</x-admin.button>
            @endcan
        </div>

        <div data-admin-table-container class="mt-5 hidden overflow-visible lg:block">
            <table class="w-full min-w-2xl text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 py-3 font-bold">{{ __('messages.actions') }}</th>
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
                            <td class="py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('view', $lead)
                                        <x-admin.table.action-item :href="route('admin.leads.show', $lead)" icon="eye">{{ __('leads.details') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="py-3"><a class="font-bold text-zinc-950 hover:text-teal-700" href="{{ route('admin.leads.show', $lead) }}">{{ $lead->full_name ?: __('dashboard.no_name') }}</a></td>
                            <td class="py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="py-3">
                                <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">{{ __('leads.crm_stages.'.$lead->crm_stage) }}</span>
                            </td>
                            <td class="py-3 text-zinc-600">{{ $lead->tenant?->name }}</td>
                            <td class="py-3 text-zinc-500"><x-jalali-date-time :value="$lead->created_at" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-zinc-500">{{ __('dashboard.empty_recent_leads') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($recentLeads as $lead)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <a class="admin-mobile-list-card-title hover:text-teal-700" href="{{ route('admin.leads.show', $lead) }}">{{ $lead->full_name ?: __('dashboard.no_name') }}</a>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $lead->phone_normalized }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('view', $lead)
                                <x-admin.table.action-item :href="route('admin.leads.show', $lead)" icon="eye">{{ __('leads.details') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('leads.fields.crm_stage')">
                            <x-admin.badge tone="teal">{{ __('leads.crm_stages.'.$lead->crm_stage) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('leads.fields.tenant')">
                            <span class="truncate">{{ $lead->tenant?->name }}</span>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('leads.fields.registered_at')">
                            <x-jalali-date-time :value="$lead->created_at" />
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="contact">{{ __('dashboard.empty_recent_leads') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>
    </section>
@endsection
