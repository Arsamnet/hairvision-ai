@extends('admin.layout')

@section('title', 'تنظیمات')
@section('page_title', 'تنظیمات')

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <h2 class="text-lg font-bold">تنظیمات کلینیک</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg bg-zinc-50 p-4">
                <p class="text-sm font-bold text-zinc-500">کلینیک فعال</p>
                <p class="mt-2 text-lg font-bold">{{ $tenant?->name ?? 'همه کلینیک‌ها' }}</p>
            </div>
            <div class="rounded-lg bg-zinc-50 p-4">
                <p class="text-sm font-bold text-zinc-500">ورود امن</p>
                <p class="mt-2 text-sm leading-7 text-zinc-700">ورود پنل با کد یکبارمصرف، هش‌شده، زمان‌دار و تک‌مصرف انجام می‌شود.</p>
            </div>
        </div>
    </section>
@endsection
