@extends('admin.layout')

@section('title', __('system.dashboard_title'))
@section('page_title', __('system.dashboard_title'))

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(__('system.metrics') as $key => $label)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <p class="text-sm font-bold text-zinc-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold text-zinc-950">{{ number_format($counts[$key]) }}</p>
            </section>
        @endforeach
    </div>
@endsection
