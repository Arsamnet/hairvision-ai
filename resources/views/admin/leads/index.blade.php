@extends('admin.layout')

@section('title', 'لیدها')
@section('page_title', 'لیدها')

@section('content')
    <section class="admin-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">مدیریت لیدها</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? 'همه کلینیک‌ها' }}</p>
            </div>
            @can('create', App\Models\Lead::class)
                @if($tenant)
                    <a href="{{ route('admin.leads.create', ['tenant_id' => $tenant->id]) }}" class="admin-button admin-button-primary">لید جدید</a>
                @endif
            @endcan
        </div>

        <form method="GET" class="mt-5 grid gap-3 lg:grid-cols-[minmax(12rem,18rem)_1fr_11rem_11rem_9rem]">
            @include('admin.partials.tenant-filter-select')
            <label class="block">
                <span class="mb-2 block text-xs font-bold text-zinc-500">جستجو</span>
                <input name="q" value="{{ request('q') }}" placeholder="نام، موبایل یا منبع" class="admin-input">
            </label>
            <label class="block">
                <span class="mb-2 block text-xs font-bold text-zinc-500">وضعیت</span>
                <select name="status" class="admin-select">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach(['new', 'verified', 'contacted', 'qualified', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-2 block text-xs font-bold text-zinc-500">مرحله</span>
                <select name="crm_stage" class="admin-select">
                    <option value="">همه مراحل</option>
                    @foreach(['new_lead', 'needs_follow_up', 'consultation_booked', 'converted', 'lost'] as $stage)
                        <option value="{{ $stage }}" @selected(request('crm_stage') === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </label>
            <button class="admin-button admin-button-primary h-11 self-end">فیلتر</button>
        </form>

        <div class="admin-table-shell mt-5">
            <div class="admin-table-scroll">
            <table class="admin-table min-w-[44rem] text-sm">
                <thead>
                    <tr>
                        <th class="no-sort w-12 py-3 font-bold" aria-label="عملیات"></th>
                        <th class="py-3 font-bold">نام</th>
                        <th class="py-3 font-bold">موبایل</th>
                        <th class="py-3 font-bold">وضعیت</th>
                        <th class="py-3 font-bold">مرحله</th>
                        <th class="py-3 font-bold">ثبت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($leads as $lead)
                        <tr>
                            <td class="py-3">
                                <div class="inline-block">
                                    <button id="lead-actions-{{ $lead->id }}" type="button" data-dropdown-toggle="lead-actions-menu-{{ $lead->id }}" data-dropdown-placement="bottom-start" class="admin-row-action-button" aria-label="عملیات {{ $lead->full_name ?: 'لید' }}">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="5" cy="12" r="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <circle cx="19" cy="12" r="2" />
                                        </svg>
                                    </button>
                                    <div id="lead-actions-menu-{{ $lead->id }}" class="z-50 hidden w-40 divide-y divide-gray-100 rounded-lg border border-gray-100 bg-white shadow-sm">
                                        <ul class="py-1 text-sm text-gray-700" aria-labelledby="lead-actions-{{ $lead->id }}">
                                            <li><a href="{{ route('admin.leads.show', $lead) }}" class="block px-4 py-2 hover:bg-gray-100">جزئیات</a></li>
                                        @can('update', $lead)
                                            <li><a href="{{ route('admin.leads.edit', $lead) }}" class="block px-4 py-2 hover:bg-gray-100">ویرایش</a></li>
                                        @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 font-bold">{{ $lead->full_name ?: 'بدون نام' }}</td>
                            <td class="py-3 text-zinc-600">{{ \App\Support\AdminDashboardFormat::phone($lead->phone_normalized) }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->status }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->crm_stage }}</td>
                            <td class="py-3 text-zinc-500" data-order="{{ $lead->created_at?->timestamp }}">{{ \App\Support\JalaliDate::readable($lead->created_at) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-sm font-bold text-zinc-500">لیدی برای نمایش وجود ندارد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if($leads->hasPages())
                <div class="border-t border-zinc-100 px-4 py-3">
                    {{ $leads->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
