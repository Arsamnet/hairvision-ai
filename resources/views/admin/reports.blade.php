@extends('admin.layout')

@section('title', 'گزارش‌ها')
@section('page_title', 'گزارش‌ها')

@section('content')
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">لیدها بر اساس مرحله</h2>
            <div class="mt-5 space-y-3">
                @forelse($stageCounts as $stage => $count)
                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3">
                        <span class="text-sm font-bold text-zinc-700">{{ $stage }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <p class="rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">داده‌ای برای گزارش وجود ندارد.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">کلینیک‌ها</h2>
            <div class="mt-5 space-y-3">
                @forelse($tenantCounts as $tenantRow)
                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3">
                        <span class="text-sm font-bold text-zinc-700">{{ $tenantRow->name }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ number_format($tenantRow->leads_count) }}</span>
                    </div>
                @empty
                    <p class="rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">گزارش چندکلینیکی فقط برای مدیر سیستم نمایش داده می‌شود.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
