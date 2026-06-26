@extends('admin.layout')

@section('title', 'تنظیمات')
@section('page_title', 'تنظیمات')

@section('content')
    <div class="space-y-5">
        <section class="admin-panel">
            <h2 class="text-lg font-bold">تنظیمات کلینیک</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="admin-info-tile">
                    <p class="text-sm font-bold text-zinc-500">کلینیک فعال</p>
                    <p class="mt-2 text-lg font-bold">{{ $tenant?->name ?? 'همه کلینیک‌ها' }}</p>
                </div>
                <div class="admin-info-tile">
                    <p class="text-sm font-bold text-zinc-500">ورود امن</p>
                    <p class="mt-2 text-sm leading-7 text-zinc-700">ورود پنل با کد یکبارمصرف، هش‌شده، زمان‌دار و تک‌مصرف انجام می‌شود.</p>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">تنظیمات پیامک</h2>
                    <p class="mt-1 text-sm leading-7 text-zinc-500">سرویس پیش‌فرض حساب شما یا سرویس اختصاصی کلینیک را از همین بخش مدیریت کنید.</p>
                </div>
                <a href="{{ route('admin.sms.providers.index') }}" class="admin-button admin-button-secondary">مدیریت سرویس‌ها</a>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-2">
                <form method="POST" action="{{ route('admin.account.sms-settings.update') }}" class="admin-filter-panel space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_redirect_to" value="settings">

                    <label class="block space-y-2">
                        <span class="text-sm font-bold text-zinc-700">سرویس پیش‌فرض</span>
                        <select name="default_sms_provider_account_id" class="admin-select">
                            <option value="">انتخاب نشده</option>
                            @foreach($smsProviders as $provider)
                                <option value="{{ $provider->id }}" @selected(old('default_sms_provider_account_id', $smsSetting->default_sms_provider_account_id) == $provider->id)>
                                    {{ $provider->name }} - {{ $provider->type?->name }} - {{ $provider->scope->value === 'system' ? 'سیستمی' : 'اختصاصی' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex items-center gap-2 text-sm font-bold text-zinc-700">
                        <input type="checkbox" name="fallback_to_system" value="1" @checked(old('fallback_to_system', $smsSetting->fallback_to_system ?? true)) class="admin-checkbox">
                        در صورت نبود سرویس اختصاصی، از سرویس سیستمی فعال استفاده شود
                    </label>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button class="admin-button admin-button-primary">ذخیره تنظیمات</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.account.sms-settings.test-send') }}" class="admin-filter-panel">
                    @csrf
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-black text-zinc-950">ارسال آزمایشی</h3>
                            <p class="mt-1 text-sm leading-7 text-zinc-500">پیام آزمایشی به شماره موبایل حساب شما ارسال می‌شود.</p>
                        </div>
                        <button class="admin-button admin-button-secondary">ارسال تست</button>
                    </div>
                </form>
            </div>
        </section>

        @can('create', App\Models\SmsProviderAccount::class)
            <section class="admin-panel">
                <h2 class="text-lg font-bold">افزودن سرویس پیامک</h2>
                <form method="POST" action="{{ route('admin.sms.providers.store') }}" class="mt-5 space-y-5">
                    @csrf
                    <input type="hidden" name="_redirect_to" value="settings">
                    @include('admin.sms.providers.partials.form', [
                        'provider' => null,
                        'providerTypes' => $smsProviderTypes,
                        'scopes' => $smsScopes,
                    ])
                    <div class="flex justify-end">
                        <button class="admin-button admin-button-primary">ذخیره سرویس</button>
                    </div>
                </form>
            </section>
        @endcan
    </div>
@endsection
