@extends('admin.layout')

@section('title', 'جزئیات لاگ پیامک')
@section('page_title', 'جزئیات لاگ پیامک')

@section('content')
<div class="max-w-4xl space-y-5">
    <div class="flex justify-end">
        <a href="{{ route('admin.sms.logs.index') }}" class="admin-button admin-button-secondary">بازگشت به لاگ‌ها</a>
    </div>

    <section class="admin-panel">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">کاربر</p>
                <p class="mt-2 font-bold">{{ $log->user?->name ?? '—' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">سرویس</p>
                <p class="mt-2 font-bold">{{ $log->providerAccount?->name ?? 'Fake/Local' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">نوع سرویس</p>
                <p class="mt-2 font-bold">{{ $log->providerType?->name ?? $log->providerAccount?->type?->name ?? '—' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">گیرنده</p>
                <p class="mt-2 font-bold">{{ \App\Support\AdminDashboardFormat::phone($log->recipient_mobile) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">وضعیت</p>
                <p class="mt-2 font-bold">{{ $log->status->value }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">هدف</p>
                <p class="mt-2 font-bold">{{ $log->purpose ?? '—' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">شناسه پیام سرویس</p>
                <p class="mt-2 font-bold">{{ $log->provider_message_id ?? '—' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">هزینه</p>
                <p class="mt-2 font-bold">{{ $log->cost ?? '—' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">زمان ثبت</p>
                <p class="mt-2 font-bold">{{ \App\Support\JalaliDate::readable($log->created_at) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">زمان ارسال</p>
                <p class="mt-2 font-bold">{{ \App\Support\JalaliDate::readable($log->sent_at) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">زمان خطا</p>
                <p class="mt-2 font-bold">{{ \App\Support\JalaliDate::readable($log->failed_at) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">کد خطا</p>
                <p class="mt-2 font-bold">{{ $log->error_code ?? '—' }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">خلاصه پیام</p>
                <p class="mt-2 text-sm leading-7 text-zinc-700">{{ $log->message_preview ?? '—' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">خطا</p>
                <p class="mt-2 text-sm leading-7 text-zinc-700">{{ $log->error_message ?? '—' }}</p>
            </div>
        </div>
    </section>
</div>
@endsection
