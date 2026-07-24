@extends('admin.layout')

@section('title', __('landing_templates.edit'))
@section('page_title', __('landing_templates.edit').' '.$landingTemplate->name)

@section('content')
    <x-admin.card>
        <form method="POST" action="{{ route('admin.system.landing-templates.update', $landingTemplate) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit_landing_template">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold">{{ $landingTemplate->name }}</h2>
                        @if($landingTemplate->is_default)
                            <x-admin.badge tone="teal">{{ __('landing_templates.fields.default') }}</x-admin.badge>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ __('landing_templates.fields.revision') }}:
                        <span dir="ltr">{{ number_format($landingTemplate->revision) }}</span>
                        ·
                        {{ __('landing_templates.fields.assignments') }}:
                        <span dir="ltr">{{ number_format($landingTemplate->theme_settings_count) }}</span>
                    </p>
                </div>
            </div>
            @include('admin.system.landing-templates.partials.form', ['landingTemplate' => $landingTemplate])
        </form>
    </x-admin.card>
@endsection
