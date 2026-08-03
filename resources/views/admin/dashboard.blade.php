{{-- @since 2.1.0 Adds the seven-day clinic performance summary. --}}
@extends('admin.layout')

@section('title', __('dashboard.admin_title'))
@section('page_title', __('dashboard.dashboard'))

@section('content')
    @php
        $planStatus = $planAccess['status']->value ?? 'inactive';
        $planMessage = __('dashboard.plan.'.$planStatus);
        $displaySubscription = $activeSubscription ?: ($planSubscription ?? null);
        $remainingCredit = app(\App\Services\Billing\ServiceEntitlementService::class)->remainingCreditForSubscription($displaySubscription);
        $performanceTotals = $recentPerformance['totals'] ?? [
            'landing_views' => 0,
            'otp_verified' => 0,
            'generation_requested' => 0,
            'conversion_rate' => 0,
        ];
        $performanceDays = $recentPerformance['daily'] ?? [];
        $performanceChartMax = $recentPerformance['chart_max'] ?? 1;
    @endphp

    <section class="mb-5 overflow-hidden rounded-lg border border-teal-100 bg-white shadow-sm">
        <div class="grid gap-0 lg:grid-cols-[1.3fr_.7fr]">
            <div class="p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700">
                            <x-admin.icon name="chart" />
                        </span>
                        <div>
                            <h2 class="text-lg font-bold">{{ __('dashboard.performance.title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('dashboard.performance.description') }}</p>
                        </div>
                    </div>
                    @if($recentPerformance)
                        @can('permission', 'reports.user.view')
                            <x-admin.button :href="route('admin.reports')" variant="secondary" size="compact" icon="report-user">
                                {{ __('dashboard.performance.view_report') }}
                            </x-admin.button>
                        @endcan
                    @endif
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach([
                        ['label' => __('dashboard.performance.landing_views'), 'value' => number_format($performanceTotals['landing_views'])],
                        ['label' => __('dashboard.performance.verified_leads'), 'value' => number_format($performanceTotals['otp_verified'])],
                        ['label' => __('dashboard.performance.generations'), 'value' => number_format($performanceTotals['generation_requested'])],
                        ['label' => __('dashboard.performance.conversion_rate'), 'value' => number_format($performanceTotals['conversion_rate'], 1).'%'],
                    ] as $metric)
                        <div class="rounded-lg bg-zinc-50 px-3 py-3 ring-1 ring-zinc-100">
                            <p class="text-[11px] font-bold leading-5 text-zinc-500">{{ $metric['label'] }}</p>
                            <p class="mt-1 text-xl font-bold text-zinc-950">{{ $metric['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if($recentPerformance)
                    <div class="mt-5 rounded-lg border border-zinc-100 bg-zinc-50/70 px-3 pb-3 pt-4" aria-label="{{ __('dashboard.performance.chart_label') }}">
                        <div class="flex h-28 items-end justify-between gap-2" dir="ltr">
                            @foreach($performanceDays as $day)
                                @php
                                    $visitHeight = $day['landing_views'] > 0
                                        ? max(4, round(($day['landing_views'] / $performanceChartMax) * 100))
                                        : 0;
                                    $generationHeight = $day['generation_requested'] > 0
                                        ? max(4, round(($day['generation_requested'] / $performanceChartMax) * 100))
                                        : 0;
                                @endphp
                                <div class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center" title="{{ __('dashboard.performance.day_tooltip', ['date' => $day['label'], 'views' => number_format($day['landing_views']), 'generations' => number_format($day['generation_requested'])]) }}">
                                    <div class="flex min-h-0 flex-1 items-end justify-center gap-1">
                                        <span class="w-2.5 rounded-t bg-teal-500" style="height: {{ $visitHeight }}%"></span>
                                        <span class="w-2.5 rounded-t bg-violet-500" style="height: {{ $generationHeight }}%"></span>
                                    </div>
                                    <span class="truncate text-[10px] font-bold text-zinc-500" dir="ltr">{{ $day['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-4 text-[11px] font-bold text-zinc-500">
                            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-teal-500"></span>{{ __('dashboard.performance.landing_views') }}</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-violet-500"></span>{{ __('dashboard.performance.generations') }}</span>
                        </div>
                    </div>
                @else
                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-7 text-amber-900">
                        {{ $planMessage }}
                    </div>
                @endif
            </div>

            <div class="border-t border-teal-100 bg-white p-5 sm:p-6 lg:border-r lg:border-t-0">
                <p class="text-xs font-bold text-zinc-500">{{ __('dashboard.plan.details_title') }}</p>
                @if($displaySubscription)
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="mt-2 text-sm font-bold text-zinc-700">{{ $displaySubscription->plan?->name }}</p>
                        @if($planStatus === 'plan_disabled')
                            <x-admin.badge tone="amber">{{ __('dashboard.plan.disabled_by_system_badge') }}</x-admin.badge>
                        @endif
                    </div>
                    <p class="mt-3 text-2xl font-bold text-zinc-950">{{ __('dashboard.plan.remaining_credit', ['count' => number_format($remainingCredit)]) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ __('dashboard.plan.active_until', ['date' => \App\Support\JalaliDate::toJalaliDate($displaySubscription->ends_at)]) }}</p>
                    @if($planStatus !== 'active')
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-6 text-amber-900">{{ $planMessage }}</p>
                    @endif
                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-bold text-zinc-500">{{ __('dashboard.applications.title') }}</p>
                        @forelse($applicationSummaries as $application)
                            <div class="rounded-lg bg-white/80 px-3 py-2 text-xs ring-1 ring-zinc-100">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-zinc-800">{{ $application['label'] }}</span>
                                    <x-admin.badge :tone="$application['available'] ? 'emerald' : 'amber'">
                                        {{ $application['available'] ? __('subscriptions.available') : __('dashboard.applications.unavailable') }}
                                    </x-admin.badge>
                                </div>
                                <p class="mt-2 leading-5 text-zinc-600">{{ $application['message'] }}</p>
                            </div>
                        @empty
                            <x-admin.empty-state icon="category" class="py-2">{{ __('dashboard.plan.no_service_credit') }}</x-admin.empty-state>
                        @endforelse
                    </div>
                @else
                    <p class="text-sm font-bold text-zinc-500">{{ $tenant?->name ?? __('dashboard.platform_overview') }}</p>
                    <p class="mt-3 text-base font-bold leading-7 text-zinc-950">{{ $planMessage }}</p>
                @endif

                @if($planStatus !== 'exempt')
                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-admin.button :href="route('admin.plans.index')" size="sm" icon="package">
                            <span>{{ __('dashboard.plan.view_plans') }}</span>
                        </x-admin.button>
                        <x-admin.button :href="route('admin.plans.index')" variant="secondary" size="sm" icon="credit-card">
                            <span>{{ $activeSubscription ? __('dashboard.plan.upgrade_plan') : __('dashboard.plan.renew_plan') }}</span>
                        </x-admin.button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => __('dashboard.cards.total_leads'), 'value' => $leadCount, 'icon' => 'contact', 'tone' => 'bg-zinc-950 text-white ring-zinc-950'],
            ['label' => __('dashboard.cards.new_leads'), 'value' => $newLeadCount, 'icon' => 'sparkles', 'tone' => 'bg-white text-zinc-950 ring-zinc-200'],
            ['label' => __('dashboard.cards.follow_up'), 'value' => $followUpCount, 'icon' => 'message', 'tone' => 'bg-white text-zinc-950 ring-zinc-200'],
            ['label' => __('dashboard.cards.successful'), 'value' => $successfulLeadCount, 'icon' => 'chart', 'tone' => 'bg-white text-zinc-950 ring-zinc-200'],
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
                        <th class="py-3 font-bold">{{ __('leads.fields.status') }}</th>
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
                                <x-admin.lead-status-badge :status="$lead->status" />
                            </td>
                            <td class="py-3 text-zinc-600">{{ $lead->tenant?->name }}</td>
                            <td class="py-3 text-zinc-500"><x-jalali-date-time :value="$lead->created_at" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10">
                                <x-admin.empty-state icon="contact">{{ __('dashboard.empty_recent_leads') }}</x-admin.empty-state>
                            </td>
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
                        <x-admin.mobile-list.field :label="__('leads.fields.status')">
                            <x-admin.lead-status-badge :status="$lead->status" />
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
