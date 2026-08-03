{{-- @since 2.1.0 Links system clinic comparisons to detailed performance reports. --}}
{{-- @since 2.1.1 Aligns clinic comparison final conversion with unique visitors converted to leads. --}}
@extends('admin.layout')

@section('title', __('reports.management_title'))
@section('page_title', __('reports.management_title'))

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-bold">{{ __('reports.management_title') }}</h2>
        <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.management_subtitle') }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        @foreach(__('reports.metrics') as $key => $label)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <p class="text-sm font-bold text-zinc-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold">{{ number_format($metrics[$key]) }}</p>
            </section>
        @endforeach
    </div>

    <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div>
            <h2 class="text-lg font-bold">{{ __('reports.clinics') }}</h2>
            <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.clinic_management_hint') }}</p>
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200">
            <table class="min-w-full text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-600">
                    <tr>
                        <th class="px-4 py-3">{{ __('reports.clinic') }}</th>
                        <th class="px-4 py-3">{{ __('reports.funnel.landing_view') }}</th>
                        <th class="px-4 py-3">{{ __('reports.unique_visitors') }}</th>
                        <th class="px-4 py-3">{{ __('reports.total_leads') }}</th>
                        <th class="px-4 py-3">{{ __('reports.funnel.generation_requested') }}</th>
                        <th class="px-4 py-3">{{ __('reports.overall_conversion_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($clinicRows as $clinicRow)
                        <tr>
                            <td class="px-4 py-3 font-bold">
                                <a class="text-zinc-900 hover:text-teal-700" href="{{ route('admin.reports', ['clinic_id' => $clinicRow['id']]) }}">
                                    {{ $clinicRow['name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ number_format($clinicRow['visits']) }}</td>
                            <td class="px-4 py-3">{{ number_format($clinicRow['unique_visitors']) }}</td>
                            <td class="px-4 py-3">{{ number_format($clinicRow['leads']) }}</td>
                            <td class="px-4 py-3">{{ number_format($clinicRow['generation_requests']) }}</td>
                            <td class="px-4 py-3 font-bold text-teal-700">{{ number_format($clinicRow['conversion_rate'], 1) }}٪</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('reports.clinics_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
