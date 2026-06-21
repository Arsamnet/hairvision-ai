@extends('admin.layout')

@section('title', 'داشبورد HairVision AI')
@section('page_title', 'داشبورد')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'کل لیدها', 'value' => $leadCount, 'tone' => 'bg-zinc-950 text-white'],
            ['label' => 'لیدهای جدید', 'value' => $newLeadCount, 'tone' => 'bg-white text-zinc-950'],
            ['label' => 'نیازمند پیگیری', 'value' => $followUpCount, 'tone' => 'bg-white text-zinc-950'],
            ['label' => 'تبدیل‌شده', 'value' => $convertedCount, 'tone' => 'bg-white text-zinc-950'],
        ] as $card)
            <section class="rounded-lg {{ $card['tone'] }} p-5 shadow-sm ring-1 ring-zinc-200">
                <p class="text-sm font-bold opacity-70">{{ $card['label'] }}</p>
                <p class="mt-4 text-3xl font-bold">{{ number_format($card['value']) }}</p>
            </section>
        @endforeach
    </div>

    <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">آخرین لیدها</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? 'نمای کل پلتفرم' }}</p>
            </div>
            <a href="{{ route('admin.leads.index') }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">مشاهده همه</a>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3 font-bold">نام</th>
                        <th class="py-3 font-bold">موبایل</th>
                        <th class="py-3 font-bold">مرحله</th>
                        <th class="py-3 font-bold">کلینیک</th>
                        <th class="py-3 font-bold">زمان ثبت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($recentLeads as $lead)
                        <tr>
                            <td class="py-3"><a class="font-bold text-zinc-950 hover:text-teal-700" href="{{ route('admin.leads.show', $lead) }}">{{ $lead->full_name ?: 'بدون نام' }}</a></td>
                            <td class="py-3 text-zinc-600">{{ $lead->phone_normalized }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->crm_stage }}</td>
                            <td class="py-3 text-zinc-600">{{ $lead->tenant?->name }}</td>
                            <td class="py-3 text-zinc-500">{{ $lead->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-zinc-500">هنوز لیدی برای این محدوده ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
