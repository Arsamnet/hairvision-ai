@extends('admin.layout')

@section('title', 'جزئیات لید')
@section('page_title', 'جزئیات لید')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1fr_22rem]">
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-teal-600">{{ $lead->tenant?->name }}</p>
                    <h2 class="mt-2 text-2xl font-bold">{{ $lead->full_name ?: 'لید بدون نام' }}</h2>
                    <p class="mt-2 text-sm text-zinc-500">{{ $lead->phone_normalized }}</p>
                </div>
                @can('update', $lead)
                    <a href="{{ route('admin.leads.edit', $lead) }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">ویرایش</a>
                @endcan
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-zinc-50 p-4">
                    <p class="text-xs font-bold text-zinc-500">وضعیت</p>
                    <p class="mt-2 font-bold">{{ $lead->status }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-4">
                    <p class="text-xs font-bold text-zinc-500">مرحله CRM</p>
                    <p class="mt-2 font-bold">{{ $lead->crm_stage }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-4">
                    <p class="text-xs font-bold text-zinc-500">منبع</p>
                    <p class="mt-2 font-bold">{{ $lead->source ?: '-' }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-4">
                    <p class="text-xs font-bold text-zinc-500">مسئول</p>
                    <p class="mt-2 font-bold">{{ $lead->assignedUser?->name ?: '-' }}</p>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-lg font-bold">پردازش‌ها</h3>
                <div class="mt-3 space-y-3">
                    @forelse($lead->generationGroups as $group)
                        <div class="rounded-lg border border-zinc-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-bold">گروه {{ $group->uuid }}</span>
                                <span class="rounded-lg bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-700">{{ $group->status }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                @foreach($group->jobs as $job)
                                    <div class="rounded-lg bg-zinc-50 p-3 text-sm">
                                        <p class="font-bold">{{ $job->period }}</p>
                                        <p class="mt-1 text-zinc-500">{{ $job->status }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">هنوز پردازشی برای این لید ثبت نشده است.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h3 class="text-lg font-bold">یادداشت‌ها</h3>
            <div class="mt-4 space-y-3">
                @forelse($lead->notes->sortByDesc('created_at') as $note)
                    <article class="rounded-lg bg-zinc-50 p-4">
                        <p class="text-sm leading-7 text-zinc-700">{{ $note->note }}</p>
                        <p class="mt-3 text-xs text-zinc-500">{{ $note->user?->name ?? 'سیستم' }} - {{ $note->created_at?->diffForHumans() }}</p>
                    </article>
                @empty
                    <p class="rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500">یادداشتی ثبت نشده است.</p>
                @endforelse
            </div>
        </aside>
    </div>
@endsection
