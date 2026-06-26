@extends('admin.layout')

@section('title', 'سرویس‌های پیامک')
@section('page_title', 'سرویس‌های پیامک')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-zinc-500">مدیریت اتصال‌های سیستمی و اختصاصی پیامک</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.sms.logs.index') }}" class="admin-button admin-button-secondary">لاگ‌ها</a>
            <a href="{{ route('admin.sms.statistics.index') }}" class="admin-button admin-button-secondary">آمار</a>
            <a href="{{ route('admin.sms.providers.create') }}" class="admin-button admin-button-primary">سرویس جدید</a>
        </div>
    </div>

    <form method="GET" class="admin-filter-panel grid gap-3 sm:grid-cols-4">
        <select name="provider_type" class="admin-select">
            <option value="">همه سرویس‌ها</option>
            @foreach($providerTypes as $type)
                <option value="{{ $type->id }}" @selected(request('provider_type') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="scope" class="admin-select">
            <option value="">همه سطح‌ها</option>
            <option value="system" @selected(request('scope') === 'system')>سیستمی</option>
            <option value="user" @selected(request('scope') === 'user')>اختصاصی</option>
        </select>
        <select name="active" class="admin-select">
            <option value="">همه وضعیت‌ها</option>
            <option value="1" @selected(request('active') === '1')>فعال</option>
            <option value="0" @selected(request('active') === '0')>غیرفعال</option>
        </select>
        <button class="admin-button admin-button-brand">فیلتر</button>
    </form>

    <div class="admin-table-shell">
        <div class="admin-table-scroll">
            <table data-admin-datatable class="admin-table text-sm">
                <thead>
                    <tr>
                        <th class="no-sort w-12 px-4 py-3 text-right" aria-label="عملیات"></th>
                        <th class="px-4 py-3 text-right">نام</th>
                        <th class="px-4 py-3 text-right">نوع</th>
                        <th class="px-4 py-3 text-right">وضعیت</th>
                        <th class="px-4 py-3 text-right">آخرین استفاده</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($providers as $provider)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="inline-block">
                                    <button id="sms-provider-actions-{{ $provider->id }}" type="button" data-dropdown-toggle="sms-provider-actions-menu-{{ $provider->id }}" data-dropdown-placement="bottom-start" class="admin-row-action-button" aria-label="عملیات {{ $provider->name }}">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="5" cy="12" r="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <circle cx="19" cy="12" r="2" />
                                        </svg>
                                    </button>
                                    <div id="sms-provider-actions-menu-{{ $provider->id }}" class="z-50 hidden w-44 divide-y divide-gray-100 rounded-lg border border-gray-100 bg-white shadow-sm">
                                        <ul class="py-1 text-sm text-gray-700" aria-labelledby="sms-provider-actions-{{ $provider->id }}">
                                        @can('update', $provider)
                                            <li><a href="{{ route('admin.sms.providers.edit', $provider) }}" class="block px-4 py-2 hover:bg-gray-100">مشاهده و ویرایش</a></li>
                                        @else
                                            <li><span class="block px-4 py-2 text-gray-400">بدون عملیات</span></li>
                                        @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-bold text-zinc-900">{{ $provider->name }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $provider->type?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="admin-badge {{ $provider->is_active ? 'admin-badge-success' : 'admin-badge-muted' }}">{{ $provider->is_active ? 'فعال' : 'غیرفعال' }}</span>
                                @if($provider->is_default)
                                    <span class="admin-badge admin-badge-brand mr-1">پیش‌فرض</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-500" data-order="{{ $provider->last_used_at?->timestamp ?? 0 }}">{{ \App\Support\JalaliDate::readable($provider->last_used_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
