@extends('admin.layout')

@section('title', __('settings.title'))
@section('page_title', __('settings.title'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <h2 class="text-lg font-bold">{{ __('settings.clinic_settings') }}</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg bg-zinc-50 p-4">
                <p class="text-sm font-bold text-zinc-500">{{ __('settings.active_clinic') }}</p>
                <p class="mt-2 text-lg font-bold">{{ $tenant?->name ?? __('dashboard.all_clinics') }}</p>
            </div>
            <div class="rounded-lg bg-zinc-50 p-4">
                <p class="text-sm font-bold text-zinc-500">{{ __('settings.secure_login') }}</p>
                <p class="mt-2 text-sm leading-7 text-zinc-700">{{ __('settings.secure_login_body') }}</p>
            </div>
        </div>
    </section>
@endsection
