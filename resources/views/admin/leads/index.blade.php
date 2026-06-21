@extends('admin.layout')

@section('title', 'لیدها')
@section('page_title', 'لیدها')

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">مدیریت لیدها</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? 'همه کلینیک‌ها' }}</p>
            </div>
            @can('create', App\Models\Lead::class)
                @if($tenant)
                    <a href="{{ route('admin.leads.create') }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">لید جدید</a>
                @endif
            @endcan
        </div>

        <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[1fr_11rem_11rem_9rem]">
            <input name="q" value="{{ request('q') }}" placeholder="جستجو نام، موبایل یا منبع" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                <option value="">همه وضعیت‌ها</option>
                @foreach(['new', 'verified', 'contacted', 'qualified', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <select name="crm_stage" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                <option value="">همه مراحل</option>
                @foreach(['new_lead', 'needs_follow_up', 'consultation_booked', 'converted', 'lost'] as $stage)
                    <option value="{{ $stage }}" @selected(request('crm_stage') === $stage)>{{ $stage }}</option>
                @endforeach
            </select>
            <button class="h-11 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white hover:bg-teal-700">فیلتر</button>
        </form>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[58rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3 font-bold">نام</th>
                        <th class="py-3 font-bold">موبایل</th>
                        <th class="py-3 font-bold">وضعیت</th>
                        <th class="py-3 font-bold">مرحله</th>
                        <th class="py-3 font-bold">منبع</th>
                        <th class="py-3 font-bold">مسئول</th>
                        <th class="py-3 font-bold">ثبت</th>
                        <th class="py-3 font-bold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($leads as $lead)
                        <tr>
                            <td class="py-3 font-bold">{{ $lead->full_name ?: 'بدون نام' }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->status }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->crm_stage }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->source ?: '-' }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->assignedUser?->name ?: '-' }}</td>
                            <td class="py-3 text-zinc-500">{{ $lead->created_at?->diffForHumans() }}</td>
                            <td class="py-3">
                                <a href="{{ route('admin.leads.show', $lead) }}" class="rounded-lg bg-zinc-100 px-3 py-2 text-xs font-bold text-zinc-800 hover:bg-teal-50 hover:text-teal-700">جزئیات</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-zinc-500">هیچ لیدی با این فیلترها پیدا نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $leads->links() }}</div>
    </section>
@endsection
