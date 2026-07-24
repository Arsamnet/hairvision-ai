@extends('admin.layout')

@section('title', __('landing_templates.create'))
@section('page_title', __('landing_templates.create'))

@section('content')
    <x-admin.card>
        <form method="POST" action="{{ route('admin.system.landing-templates.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="_form" value="create_landing_template">
            <div>
                <h2 class="text-lg font-bold">{{ __('landing_templates.create') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('landing_templates.subtitle') }}</p>
            </div>
            @include('admin.system.landing-templates.partials.form')
        </form>
    </x-admin.card>
@endsection
