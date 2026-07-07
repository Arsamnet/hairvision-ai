@extends('admin.layout')

@section('title', __('clinics.edit'))
@section('page_title', __('clinics.edit'))

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $clinic->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-500" dir="ltr">{{ $primaryDomain?->domain ?? $clinic->slug }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-admin.button :href="route('admin.clinics.show', $clinic)" variant="secondary" size="sm" icon="eye">
                        <span>{{ __('clinics.view') }}</span>
                    </x-admin.button>
                    <x-admin.button :href="route('admin.clinics.index')" variant="secondary" size="sm" icon="building">
                        <span>{{ __('clinics.title') }}</span>
                    </x-admin.button>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('clinics.edit') }}</h3>
            <form method="POST" action="{{ route('admin.clinics.update', $clinic) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                @method('PUT')
                @include('admin.clinics.form', [
                    'clinic' => $clinic,
                    'primaryDomain' => $primaryDomain,
                    'formKey' => 'edit_clinic',
                ])
                <x-admin.button type="submit" icon="check" class="md:col-span-2">
                    <span>{{ __('system.save') }}</span>
                </x-admin.button>
            </form>
        </x-admin.card>
    </div>
@endsection
