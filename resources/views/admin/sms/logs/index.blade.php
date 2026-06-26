@extends('admin.layout')

@section('title', 'لاگ پیامک')
@section('page_title', 'لاگ پیامک')

@section('content')
<div class="space-y-5">
    <div class="flex justify-between gap-3">
        <a href="{{ route('admin.sms.providers.index') }}" class="admin-button admin-button-secondary">سرویس‌ها</a>
        <a href="{{ route('admin.sms.statistics.index') }}" class="admin-button admin-button-primary">آمار</a>
    </div>
    <form method="GET" class="admin-filter-panel grid gap-3 sm:grid-cols-5">
        <div>
            <input id="sms-log-from-value" type="hidden" name="from" value="{{ request('from') }}">
            <input data-jalali-datepicker data-target="#sms-log-from-value" value="{{ \App\Support\JalaliDate::inputValue(request('from')) }}" placeholder="از تاریخ" class="admin-input">
        </div>
        <div>
            <input id="sms-log-to-value" type="hidden" name="to" value="{{ request('to') }}">
            <input data-jalali-datepicker data-target="#sms-log-to-value" value="{{ \App\Support\JalaliDate::inputValue(request('to')) }}" placeholder="تا تاریخ" class="admin-input">
        </div>
        <select name="status" class="admin-select">
            <option value="">همه وضعیت‌ها</option>
            @foreach(['sent' => 'ارسال شده', 'failed' => 'ناموفق', 'queued' => 'در صف', 'pending' => 'در انتظار'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="provider" class="admin-select">
            <option value="">همه سرویس‌ها</option>
            @foreach($providers as $provider)
                <option value="{{ $provider->id }}" @selected(request('provider') == $provider->id)>{{ $provider->name }}</option>
            @endforeach
        </select>
        <button class="admin-button admin-button-brand">فیلتر</button>
    </form>
    <div class="admin-table-shell">
        <div class="admin-table-scroll">
            <table data-admin-datatable class="admin-table text-sm">
                <thead>
                    <tr>
                        <th class="no-sort w-12 px-4 py-3 text-right" aria-label="عملیات"></th>
                        <th class="px-4 py-3 text-right">کاربر</th>
                        <th class="px-4 py-3 text-right">سرویس</th>
                        <th class="px-4 py-3 text-right">گیرنده</th>
                        <th class="px-4 py-3 text-right">وضعیت</th>
                        <th class="px-4 py-3 text-right">زمان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($logs as $log)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="inline-block">
                                    <button id="sms-log-actions-{{ $log->id }}" type="button" data-dropdown-toggle="sms-log-actions-menu-{{ $log->id }}" data-dropdown-placement="bottom-start" class="admin-row-action-button" aria-label="عملیات لاگ پیامک">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="5" cy="12" r="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <circle cx="19" cy="12" r="2" />
                                        </svg>
                                    </button>
                                    <div id="sms-log-actions-menu-{{ $log->id }}" class="z-50 hidden w-40 divide-y divide-gray-100 rounded-lg border border-gray-100 bg-white shadow-sm">
                                        <ul class="py-1 text-sm text-gray-700" aria-labelledby="sms-log-actions-{{ $log->id }}">
                                            <li><a href="{{ route('admin.sms.logs.show', $log) }}" class="block px-4 py-2 hover:bg-gray-100">جزئیات</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-700">{{ $log->providerAccount?->name ?? 'Fake/Local' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ \App\Support\AdminDashboardFormat::phone($log->recipient_mobile) }}</td>
                            <td class="px-4 py-3 font-bold {{ $log->status->value === 'sent' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $log->status->value }}</td>
                            <td class="px-4 py-3 text-zinc-500" data-order="{{ $log->created_at?->timestamp }}">{{ \App\Support\JalaliDate::readable($log->created_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
