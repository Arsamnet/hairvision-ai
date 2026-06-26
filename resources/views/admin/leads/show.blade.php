@extends('admin.layout')

@section('title', 'جزئیات لید')
@section('page_title', 'جزئیات لید')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1fr_22rem]">
        <section class="admin-panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-blue-700">{{ $lead->tenant?->name }}</p>
                    <h2 class="mt-2 text-2xl font-bold">{{ $lead->full_name ?: 'لید بدون نام' }}</h2>
                    <p class="mt-2 text-sm text-zinc-500">{{ \App\Support\AdminDashboardFormat::phone($lead->phone_normalized) }}</p>
                </div>
                @can('update', $lead)
                    <a href="{{ route('admin.leads.edit', $lead) }}" class="admin-button admin-button-primary">ویرایش</a>
                @endcan
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="admin-info-tile">
                    <p class="text-xs font-bold text-zinc-500">وضعیت</p>
                    <p class="mt-2 font-bold">{{ $lead->status }}</p>
                </div>
                <div class="admin-info-tile">
                    <p class="text-xs font-bold text-zinc-500">مرحله CRM</p>
                    <p class="mt-2 font-bold">{{ $lead->crm_stage }}</p>
                </div>
                <div class="admin-info-tile">
                    <p class="text-xs font-bold text-zinc-500">منبع</p>
                    <p class="mt-2 font-bold">{{ $lead->source ?: '-' }}</p>
                </div>
                <div class="admin-info-tile">
                    <p class="text-xs font-bold text-zinc-500">مسئول</p>
                    <p class="mt-2 font-bold">{{ $lead->assignedUser?->name ?: '-' }}</p>
                </div>
                <div class="admin-info-tile">
                    <p class="text-xs font-bold text-zinc-500">زمان ثبت</p>
                    <p class="mt-2 font-bold">{{ \App\Support\JalaliDate::readable($lead->created_at) }}</p>
                </div>
                <div class="admin-info-tile">
                    <p class="text-xs font-bold text-zinc-500">آخرین تغییر</p>
                    <p class="mt-2 font-bold">{{ \App\Support\JalaliDate::readable($lead->updated_at) }}</p>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-lg font-bold">پردازش‌ها</h3>
                <div class="mt-3 space-y-3">
                    @forelse($lead->generationGroups as $group)
                        <div class="admin-filter-panel">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-bold">گروه {{ $group->uuid }}</span>
                                <span class="admin-badge admin-badge-neutral">{{ $group->status }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                @foreach($group->jobs as $job)
                                    <div class="admin-info-tile text-sm">
                                        <p class="font-bold">{{ $job->period }}</p>
                                        <p class="mt-1 text-zinc-500">{{ $job->status }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="admin-empty">هنوز پردازشی برای این لید ثبت نشده است.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="admin-panel">
            <h3 class="text-lg font-bold">یادداشت‌ها</h3>
            <div class="mt-4 space-y-3">
                @forelse($lead->notes->sortByDesc('created_at') as $note)
                    <article class="admin-info-tile">
                        <p class="text-sm leading-7 text-zinc-700">{{ $note->note }}</p>
                        <p class="mt-3 text-xs text-zinc-500">{{ $note->user?->name ?? 'سیستم' }} - {{ \App\Support\JalaliDate::readable($note->created_at) }}</p>
                    </article>
                @empty
                    <p class="admin-empty">یادداشتی ثبت نشده است.</p>
                @endforelse
            </div>
        </aside>
    </div>
@endsection
