@extends('admin.layout')

@section('title', __('reports.title'))
@section('page_title', __('reports.title'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('reports.leads_by_stage') }}</h2>
            <div class="mt-5 space-y-3">
                @forelse($stageCounts as $stage => $count)
                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3">
                        <span class="text-sm font-bold text-zinc-700">{{ __('leads.crm_stages.'.$stage) }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <p class="rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">{{ __('reports.empty') }}</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('reports.clinics') }}</h2>
            <div class="mt-5 space-y-3">
                @forelse($tenantCounts as $tenantRow)
                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3">
                        <span class="text-sm font-bold text-zinc-700">{{ $tenantRow->name }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ number_format($tenantRow->leads_count) }}</span>
                    </div>
                @empty
                    <p class="rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">{{ __('reports.management_only') }}</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
