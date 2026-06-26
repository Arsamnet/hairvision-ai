@extends('admin.layout')

@section('title', 'گزارش‌ها')
@section('page_title', 'گزارش‌ها')

@section('content')
    @if(auth()->user()?->isSuperAdmin())
        <form method="GET" class="admin-filter-panel mb-5 grid gap-3 sm:grid-cols-[minmax(14rem,22rem)_8rem]">
            @include('admin.partials.tenant-filter-select')
            <button class="admin-button admin-button-primary h-11 self-end">اعمال</button>
        </form>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="admin-panel">
            <h2 class="text-lg font-bold">لیدها بر اساس مرحله</h2>
            <div class="mt-5 space-y-3">
                @forelse($stageCounts as $stage => $count)
                    <div class="admin-info-tile flex items-center justify-between">
                        <span class="text-sm font-bold text-zinc-700">{{ $stage }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ \App\Support\AdminDashboardFormat::number($count) }}</span>
                    </div>
                @empty
                    <p class="admin-empty">داده‌ای برای گزارش وجود ندارد.</p>
                @endforelse
            </div>
        </section>

        <section class="admin-panel">
            <h2 class="text-lg font-bold">کلینیک‌ها</h2>
            <div class="mt-5 space-y-3">
                @forelse($tenantCounts as $tenantRow)
                    <div class="admin-info-tile flex items-center justify-between">
                        <span class="text-sm font-bold text-zinc-700">{{ $tenantRow->name }}</span>
                        <span class="text-sm font-bold text-zinc-950">{{ \App\Support\AdminDashboardFormat::number($tenantRow->leads_count) }}</span>
                    </div>
                @empty
                    <p class="admin-empty">گزارش چندکلینیکی فقط برای مدیر سیستم نمایش داده می‌شود.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
