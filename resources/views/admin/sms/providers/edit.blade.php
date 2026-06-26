@extends('admin.layout')

@section('title', 'ویرایش سرویس پیامک')
@section('page_title', 'ویرایش سرویس پیامک')

@section('content')
<div class="max-w-3xl space-y-5">
    <section class="admin-panel">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">سطح</p>
                <p class="mt-2 font-bold">{{ $provider->scope->value === 'system' ? 'سیستمی' : 'اختصاصی' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">مالک</p>
                <p class="mt-2 font-bold">{{ $provider->owner?->name ?? 'HairVision' }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">ارسال موفق</p>
                <p class="mt-2 font-bold">{{ \App\Support\AdminDashboardFormat::digits($provider->sent_count) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">ارسال ناموفق</p>
                <p class="mt-2 font-bold">{{ \App\Support\AdminDashboardFormat::digits($provider->failed_count) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">آخرین استفاده</p>
                <p class="mt-2 font-bold">{{ \App\Support\JalaliDate::readable($provider->last_used_at) }}</p>
            </div>
            <div class="admin-info-tile">
                <p class="text-xs font-bold text-zinc-500">شماره ارسال‌کننده</p>
                <p class="mt-2 font-bold">{{ $provider->sender_number ?: '—' }}</p>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.sms.providers.update', $provider) }}" class="admin-panel space-y-5">
        @csrf
        @method('PUT')
        @include('admin.sms.providers.partials.form', ['provider' => $provider, 'providerTypes' => collect([$provider->type]), 'scopes' => [$provider->scope->value => $provider->scope->value === 'system' ? 'سیستمی' : 'اختصاصی']])
        <div class="admin-info-tile text-sm text-zinc-600">
            کلیدهای ذخیره‌شده:
            @forelse($provider->maskedCredentials() as $key => $masked)
                <span class="mx-1 font-bold">{{ $key }}: {{ $masked }}</span>
            @empty
                <span>ثبت نشده</span>
            @endforelse
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.sms.providers.index') }}" class="admin-button admin-button-secondary">بازگشت</a>
            <button class="admin-button admin-button-primary">ذخیره تغییرات</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.sms.providers.destroy', $provider) }}" class="text-left">
        @csrf
        @method('DELETE')
        <button class="admin-button admin-button-danger">حذف سرویس</button>
    </form>
</div>
@endsection
