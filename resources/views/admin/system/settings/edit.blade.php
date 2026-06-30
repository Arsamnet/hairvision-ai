@extends('admin.layout')

@section('title', __('system.settings_title'))
@section('page_title', __('system.settings_title'))

@section('content')
    <form method="POST" action="{{ route('admin.system.settings.update') }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        @csrf
        @method('PUT')
        <div class="grid gap-5 md:grid-cols-2">
            @foreach($settings as $group => $groupSettings)
                <section class="rounded-lg border border-zinc-200 p-4">
                    <h2 class="font-bold">{{ $group }}</h2>
                    <div class="mt-4 space-y-4">
                        @foreach($groupSettings as $setting)
                            <label class="block">
                                <span class="text-sm font-bold text-zinc-600">{{ $setting->label }}</span>
                                <input name="settings[{{ $setting->key }}]" value="{{ old('settings.'.$setting->key, $setting->value_json['value'] ?? '') }}" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" dir="ltr">
                                <span class="mt-1 block text-xs text-zinc-500">{{ $setting->description }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        <x-admin.button type="submit" size="compact-md" class="mt-5">{{ __('system.save_settings') }}</x-admin.button>
    </form>
@endsection
