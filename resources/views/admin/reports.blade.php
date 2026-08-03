{{-- @since 2.1.0 Introduces tenant and system-scoped landing performance reporting. --}}
{{-- @since 2.1.1 Presents unique-visitor-to-lead conversion as the final KPI. --}}
@extends('admin.layout')

@section('title', __('reports.title'))
@section('page_title', __('reports.title'))

@section('content')
    @php
        $reportScopeLabel = $reportTenant?->name ?? __('reports.all_clinics');
        $exportParams = [
            'date_from' => $range['date_from'],
            'date_until' => $range['date_until'],
        ];

        if ($isSystemReport && $reportTenant) {
            $exportParams['clinic_id'] = $reportTenant->id;
        }

        $quickRangeScopeParams = $isSystemReport && $reportTenant
            ? ['clinic_id' => $reportTenant->id]
            : [];
        $currentMonthParams = array_merge($quickRangeScopeParams, $quickRanges['current_month']);
        $previousMonthParams = array_merge($quickRangeScopeParams, $quickRanges['previous_month']);
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold">{{ __('reports.title') }}</h2>
            <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.subtitle') }}</p>
            <p class="mt-2 inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">
                <x-admin.icon name="building" class="h-3.5 w-3.5" />
                {{ __('reports.report_scope') }}: {{ $reportScopeLabel }}
            </p>
        </div>

        <x-admin.button
            :href="route('admin.reports.export', $exportParams)"
            variant="secondary"
            icon="arrow-down"
        >
            {{ __('reports.export_csv') }}
        </x-admin.button>
    </div>

    <form method="GET" action="{{ route('admin.reports') }}" class="mt-5 grid gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-zinc-200 md:grid-cols-2 md:items-end {{ $isSystemReport ? 'xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto]' : 'xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]' }}">
        @if($isSystemReport)
            <label class="grid gap-1 text-sm font-bold text-zinc-700">
                <span>{{ __('reports.filter_clinic') }}</span>
                <select name="clinic_id" class="h-11 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500">
                    <option value="">{{ __('reports.all_clinics') }}</option>
                    @foreach($reportTenants as $tenantOption)
                        <option value="{{ $tenantOption->id }}" @selected((string) request('clinic_id') === (string) $tenantOption->id)>{{ $tenantOption->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="grid gap-1 text-sm font-bold text-zinc-700">
            <span>{{ __('reports.date_from') }}</span>
            <span class="relative block">
                <x-admin.icon name="calendar" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                <input name="date_from" value="{{ request('date_from', $range['date_from']) }}" inputmode="numeric" dir="ltr" data-jalali-date class="h-11 w-full rounded-lg border border-zinc-200 py-0 pr-10 pl-3 text-sm outline-none focus:border-teal-500" placeholder="۱۴۰۵/۰۱/۰۱">
            </span>
        </label>
        <label class="grid gap-1 text-sm font-bold text-zinc-700">
            <span>{{ __('reports.date_until') }}</span>
            <span class="relative block">
                <x-admin.icon name="calendar" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                <input name="date_until" value="{{ request('date_until', $range['date_until']) }}" inputmode="numeric" dir="ltr" data-jalali-date class="h-11 w-full rounded-lg border border-zinc-200 py-0 pr-10 pl-3 text-sm outline-none focus:border-teal-500" placeholder="۱۴۰۵/۱۲/۲۹">
            </span>
        </label>
        <x-admin.button type="submit" icon="filter">{{ __('reports.apply_range') }}</x-admin.button>
        <div class="flex flex-wrap items-center gap-2" data-performance-quick-ranges>
            <x-admin.button :href="route('admin.reports', $currentMonthParams)" variant="secondary" size="compact-sm">
                {{ __('reports.current_month') }}
            </x-admin.button>
            <x-admin.button :href="route('admin.reports', $previousMonthParams)" variant="secondary" size="compact-sm">
                {{ __('reports.previous_month') }}
            </x-admin.button>
            <x-admin.button :href="route('admin.reports', $quickRangeScopeParams)" variant="secondary" size="compact-sm">
                {{ __('reports.default_range') }}
            </x-admin.button>
        </div>
    </form>

    @php
            $totals = $report['totals'];
            $summaryCards = [
                ['label' => __('reports.funnel.landing_view'), 'value' => $totals['landing_views'], 'suffix' => null],
                ['label' => __('reports.unique_visitors'), 'value' => $totals['unique_visitors'], 'suffix' => null],
                ['label' => __('reports.total_application_clicks'), 'value' => $totals['application_clicks'], 'suffix' => null],
                ['label' => __('reports.funnel.phone_submitted'), 'value' => $totals['phone_submitted'], 'suffix' => null],
                ['label' => __('reports.funnel.photo_uploaded'), 'value' => $totals['photo_uploaded'], 'suffix' => null],
                ['label' => __('reports.total_leads'), 'value' => $totals['total_leads'], 'suffix' => null],
                ['label' => __('reports.overall_conversion_rate'), 'value' => $totals['overall_conversion_rate'], 'suffix' => '٪'],
            ];
    @endphp

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
            @foreach($summaryCards as $card)
                <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-zinc-200">
                    <p class="text-xs font-bold leading-6 text-zinc-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-950" dir="ltr">{{ number_format($card['value'], is_float($card['value']) ? 1 : 0) }}{{ $card['suffix'] }}</p>
                </section>
            @endforeach
        </div>

        <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div>
                <h2 class="text-lg font-bold">{{ __('reports.funnel_title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.funnel_hint') }}</p>
            </div>

            <div class="mt-5 space-y-3">
                @foreach($report['funnel'] as $index => $step)
                    <article class="rounded-lg border border-zinc-200 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">{{ number_format($index + 1) }}</span>
                                <span class="text-sm font-bold text-zinc-800">{{ __('reports.funnel.'.$step['key']) }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs font-bold">
                                <span class="text-zinc-950">{{ number_format($step['count']) }} {{ __('reports.visitor') }}</span>
                                @if($index > 0)
                                    <span class="text-teal-700">{{ number_format($step['from_previous_rate'], 1) }}٪ {{ __('reports.from_previous') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-zinc-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $step['from_landing_rate'] }}">
                            <div class="h-full rounded-full bg-linear-to-l from-violet-600 to-teal-500" style="width: {{ $step['from_landing_rate'] }}%"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @php
            $series = [
                'landing_views' => ['label' => __('reports.funnel.landing_view'), 'color' => '#7c3aed'],
                'phone_submitted' => ['label' => __('reports.funnel.phone_submitted'), 'color' => '#2563eb'],
                'photo_uploaded' => ['label' => __('reports.funnel.photo_uploaded'), 'color' => '#0d9488'],
                'generation_requested' => ['label' => __('reports.funnel.generation_requested'), 'color' => '#ea580c'],
            ];
        @endphp

        <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('reports.monthly_chart_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.monthly_chart_hint') }}</p>
                </div>
                <div class="flex flex-wrap gap-3 text-xs font-bold text-zinc-600">
                    @foreach($series as $definition)
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $definition['color'] }}"></span>{{ $definition['label'] }}</span>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 overflow-x-auto" dir="ltr">
                <div class="flex h-72 min-w-192 items-end gap-3 border-b border-zinc-300 bg-[repeating-linear-gradient(to_top,transparent_0,transparent_24%,#e4e4e7_25%)] px-3 pt-4" role="img" aria-label="{{ __('reports.monthly_chart_accessible') }}">
                    @foreach($report['monthly'] as $row)
                        <div class="flex h-full min-w-14 flex-1 flex-col justify-end gap-2">
                            <div class="flex min-h-0 flex-1 items-end justify-center gap-1">
                                @foreach($series as $key => $definition)
                                    @php
                                        $height = $row[$key] > 0
                                            ? max(2, round(($row[$key] / $report['chart_max']) * 100, 2))
                                            : 0;
                                    @endphp
                                    <span
                                        class="block min-w-1.5 flex-1 rounded-t-sm"
                                        style="height: {{ $height }}%; background-color: {{ $definition['color'] }}"
                                        title="{{ $definition['label'] }}: {{ number_format($row[$key]) }}"
                                    ><span class="sr-only">{{ $definition['label'] }}: {{ number_format($row[$key]) }}</span></span>
                                @endforeach
                            </div>
                            <span class="shrink-0 pb-2 text-center text-xs font-bold text-zinc-600">{{ $row['month'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div>
                <h2 class="text-lg font-bold">{{ __('reports.application_clicks_title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.application_clicks_hint') }}</p>
            </div>

            <div class="mt-5 grid gap-3 lg:grid-cols-2">
                @forelse($report['applications'] as $application)
                    <article class="rounded-lg border border-zinc-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-zinc-900">{{ $application['name'] }}</h3>
                                <p class="mt-1 text-xs text-zinc-500" dir="ltr">{{ $application['code'] }}</p>
                            </div>
                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ number_format($application['clicks']) }} {{ __('reports.click') }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-zinc-50 p-2"><dt class="text-xs text-zinc-500">{{ __('reports.unique_visitors') }}</dt><dd class="mt-1 font-bold">{{ number_format($application['unique_visitors']) }}</dd></div>
                            <div class="rounded-lg bg-zinc-50 p-2"><dt class="text-xs text-zinc-500">{{ __('reports.generation_visitors') }}</dt><dd class="mt-1 font-bold">{{ number_format($application['generation_visitors']) }}</dd></div>
                            <div class="rounded-lg bg-zinc-50 p-2"><dt class="text-xs text-zinc-500">{{ __('reports.conversion') }}</dt><dd class="mt-1 font-bold">{{ number_format($application['conversion_rate'], 1) }}٪</dd></div>
                        </dl>
                    </article>
                @empty
                    <x-admin.empty-state icon="chart" class="lg:col-span-2">{{ __('reports.no_application_clicks') }}</x-admin.empty-state>
                @endforelse
            </div>
        </section>

        <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('reports.monthly_table_title') }}</h2>
            <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200">
                <table class="min-w-full text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-600">
                        <tr>
                            <th class="px-3 py-3">{{ __('reports.month') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.landing_view') }}</th>
                            <th class="px-3 py-3">{{ __('reports.unique_visitors') }}</th>
                            <th class="px-3 py-3">{{ __('reports.total_leads') }}</th>
                            <th class="px-3 py-3">{{ __('reports.total_application_clicks') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.photo_selected') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.phone_submitted') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.otp_verified') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.photo_uploaded') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.generation_requested') }}</th>
                            <th class="px-3 py-3">{{ __('reports.funnel.result_viewed') }}</th>
                            <th class="px-3 py-3">{{ __('reports.overall_conversion_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($report['monthly'] as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-3 font-bold" dir="ltr">{{ $row['month'] }}</td>
                                <td class="px-3 py-3">{{ number_format($row['landing_views']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['unique_visitors']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['total_leads']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['application_clicks']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['photo_selected']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['phone_submitted']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['otp_verified']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['photo_uploaded']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['generation_requested']) }}</td>
                                <td class="px-3 py-3">{{ number_format($row['result_viewed']) }}</td>
                                <td class="px-3 py-3 font-bold text-teal-700">{{ number_format($row['conversion_rate'], 1) }}٪</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('reports.leads_by_status') }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($report['statuses'] as $status => $count)
                    <div class="flex items-center justify-between rounded-lg {{ $status === 'canceled' ? 'bg-rose-50' : 'bg-zinc-50' }} px-4 py-3">
                        <x-admin.lead-status-badge :status="$status" />
                        <span class="text-sm font-bold text-zinc-950">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <x-admin.empty-state icon="chart" class="sm:col-span-2 lg:col-span-4">{{ __('reports.empty') }}</x-admin.empty-state>
                @endforelse
            </div>
        </section>
@endsection
