@extends('admin.layout')

@section('title', 'تنظیمات پیامک من')
@section('page_title', 'تنظیمات پیامک من')

@section('content')
<form method="POST" action="{{ route('admin.account.sms-settings.update') }}" class="admin-panel max-w-2xl space-y-5">
    @csrf
    @method('PUT')
    <label class="block space-y-2">
        <span class="text-sm font-bold text-zinc-700">سرویس پیش‌فرض</span>
        <select name="default_sms_provider_account_id" class="admin-select">
            <option value="">انتخاب نشده</option>
            @foreach($providers as $provider)
                <option value="{{ $provider->id }}" @selected(old('default_sms_provider_account_id', $setting->default_sms_provider_account_id) == $provider->id)>
                    {{ $provider->name }} - {{ $provider->type?->name }} - {{ $provider->scope->value === 'system' ? 'سیستمی' : 'اختصاصی' }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="flex items-center gap-2 text-sm font-bold text-zinc-700">
        <input type="checkbox" name="fallback_to_system" value="1" @checked(old('fallback_to_system', $setting->fallback_to_system ?? true)) class="admin-checkbox">
        در صورت نبود سرویس اختصاصی، از سرویس سیستمی فعال استفاده شود
    </label>
    <div class="flex justify-between gap-2">
        <a href="{{ route('admin.sms.providers.create') }}" class="admin-button admin-button-secondary">ساخت سرویس اختصاصی</a>
        <button class="admin-button admin-button-primary">ذخیره</button>
    </div>
</form>

<form method="POST" action="{{ route('admin.account.sms-settings.test-send') }}" class="admin-panel mt-4 max-w-2xl">
    @csrf
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-black text-zinc-950">ارسال آزمایشی</h2>
            <p class="mt-1 text-sm text-zinc-500">پیام آزمایشی به شماره موبایل حساب شما ارسال می‌شود.</p>
        </div>
        <button class="admin-button admin-button-secondary">ارسال تست</button>
    </div>
</form>
@endsection
