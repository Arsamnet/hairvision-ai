@extends('admin.layout')

@section('title', __('reports.management_title'))
@section('page_title', __('reports.management_title'))

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-bold">{{ __('reports.management_title') }}</h2>
        <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('reports.management_subtitle') }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        @foreach(__('reports.metrics') as $key => $label)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <p class="text-sm font-bold text-zinc-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold">{{ number_format($metrics[$key]) }}</p>
            </section>
        @endforeach
    </div>
@endsection
