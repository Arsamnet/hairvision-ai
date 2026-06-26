@extends('admin.layout')

@section('title', 'داشبورد HairVision AI')
@section('page_title', 'داشبورد')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-blue-700">نمای مدیریتی HairVision AI</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">داشبورد عملیات کلینیک</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $tenant?->name ?? 'نمای کل پلتفرم' }}</p>
        </div>
        <a href="{{ route('admin.leads.index') }}" class="admin-button admin-button-secondary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h10" />
            </svg>
            مدیریت لیدها
        </a>
    </div>

    @if(auth()->user()?->isSuperAdmin())
        <form method="GET" class="admin-filter-panel mb-5 grid gap-3 sm:grid-cols-[minmax(14rem,22rem)_8rem]">
            @include('admin.partials.tenant-filter-select')
            <button class="admin-button admin-button-primary h-11 self-end">اعمال</button>
        </form>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'کل لیدها', 'value' => $leadCount, 'tone' => 'admin-stat-card-primary', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87'],
            ['label' => 'لیدهای جدید', 'value' => $newLeadCount, 'tone' => '', 'icon' => 'M12 5v14M5 12h14'],
            ['label' => 'نیازمند پیگیری', 'value' => $followUpCount, 'tone' => '', 'icon' => 'M12 8v5l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['label' => 'تبدیل‌شده', 'value' => $convertedCount, 'tone' => '', 'icon' => 'M20 6 9 17l-5-5'],
        ] as $card)
            <section class="admin-stat-card {{ $card['tone'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold opacity-70">{{ $card['label'] }}</p>
                        <p class="mt-3 text-3xl font-black">{{ \App\Support\AdminDashboardFormat::number($card['value']) }}</p>
                    </div>
                    <span class="admin-stat-icon">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $card['icon'] }}" />
                        </svg>
                    </span>
                </div>
            </section>
        @endforeach
    </div>

    <section class="admin-panel mt-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-950">آخرین لیدها</h2>
                <p class="mt-1 text-sm text-slate-500">پیگیری سریع تازه‌ترین ثبت‌ها</p>
            </div>
            <a href="{{ route('admin.leads.index') }}" class="admin-button admin-button-primary">مشاهده همه</a>
        </div>

        <div class="admin-table-shell mt-5">
            <div class="admin-table-scroll">
            <table data-admin-datatable data-table-mode="compact" data-page-length="5" class="admin-table min-w-[38rem] text-sm">
                <thead>
                    <tr>
                        <th class="no-sort w-12 py-3 font-bold" aria-label="عملیات"></th>
                        <th class="py-3 font-bold">نام</th>
                        <th class="py-3 font-bold">موبایل</th>
                        <th class="py-3 font-bold">مرحله</th>
                        <th class="py-3 font-bold">زمان ثبت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($recentLeads as $lead)
                        <tr>
                            <td class="py-3">
                                <div class="inline-block">
                                    <button id="dashboard-lead-actions-{{ $lead->id }}" type="button" data-dropdown-toggle="dashboard-lead-actions-menu-{{ $lead->id }}" data-dropdown-placement="bottom-start" class="admin-row-action-button" aria-label="عملیات {{ $lead->full_name ?: 'لید' }}">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="5" cy="12" r="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <circle cx="19" cy="12" r="2" />
                                        </svg>
                                    </button>
                                    <div id="dashboard-lead-actions-menu-{{ $lead->id }}" class="z-50 hidden w-40 divide-y divide-gray-100 rounded-lg border border-gray-100 bg-white shadow-sm">
                                        <ul class="py-1 text-sm text-gray-700" aria-labelledby="dashboard-lead-actions-{{ $lead->id }}">
                                            <li><a href="{{ route('admin.leads.show', $lead) }}" class="block px-4 py-2 hover:bg-gray-100">جزئیات</a></li>
                                        @can('update', $lead)
                                            <li><a href="{{ route('admin.leads.edit', $lead) }}" class="block px-4 py-2 hover:bg-gray-100">ویرایش</a></li>
                                        @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 font-bold text-zinc-950">{{ $lead->full_name ?: 'بدون نام' }}</td>
                            <td class="py-3 text-zinc-600">{{ \App\Support\AdminDashboardFormat::phone($lead->phone_normalized) }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->crm_stage }}</td>
                            <td class="py-3 text-zinc-500" data-order="{{ $lead->created_at?->timestamp }}">{{ \App\Support\JalaliDate::readable($lead->created_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </section>
@endsection
