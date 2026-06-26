@extends('admin.layout')

@section('title', 'آمار پیامک')
@section('page_title', 'آمار پیامک')

@section('content')
<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-4">
        @foreach([['کل', $total], ['ارسال موفق', $sent], ['ناموفق', $failed], ['نرخ موفقیت', $successRate.'٪']] as [$label, $value])
            <div class="admin-stat-card">
                <p class="text-sm font-bold text-zinc-500">{{ $label }}</p>
                <p class="mt-3 text-2xl font-black text-zinc-950">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="admin-panel">
            <h2 class="text-base font-black">مصرف بر اساس سرویس</h2>
            <div class="mt-4 space-y-3">
                @forelse($byProvider as $row)
                    <div class="flex justify-between border-b border-zinc-100 pb-2 text-sm">
                        <span>{{ $row->providerAccount?->name ?? 'Fake/Local' }}</span>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">داده‌ای وجود ندارد.</p>
                @endforelse
            </div>
        </div>
        <div class="admin-panel">
            <h2 class="text-base font-black">مصرف بر اساس کاربر</h2>
            <div class="mt-4 space-y-3">
                @forelse($byUser as $row)
                    <div class="flex justify-between border-b border-zinc-100 pb-2 text-sm">
                        <span>{{ $row->user?->name ?? '—' }}</span>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">برای حساب شما گزارش کاربری جداگانه نمایش داده نمی‌شود.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
