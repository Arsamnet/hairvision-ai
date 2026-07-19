@extends('admin.layout')

@section('title', __('system.settings_title'))
@section('page_title', __('system.settings_title'))

@section('content')
    <form method="POST" action="{{ route('admin.system.settings.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('system.settings_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('settings.hint') }}</p>
                </div>
                <x-admin.button type="submit" icon="check">{{ __('system.save_settings') }}</x-admin.button>
            </div>
        </x-admin.card>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($settings as $group => $groupSettings)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                    <div class="flex items-center gap-2">
                        <x-admin.icon name="settings" class="h-5 w-5 text-teal-700" />
                        <h2 class="font-bold">{{ __('settings.groups.'.$group) !== 'settings.groups.'.$group ? __('settings.groups.'.$group) : $group }}</h2>
                    </div>
                    <div class="mt-4 space-y-4">
                        @foreach($groupSettings as $setting)
                            @php
                                $value = old('settings.'.$setting->key, $setting->value_json['value'] ?? '');
                                $displayValue = is_array($value)
                                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                    : (string) $value;
                            @endphp
                            <label class="block">
                                <span class="text-sm font-bold text-zinc-600">{{ $setting->label }}</span>
                                @if($setting->type === 'json')
                                    <textarea name="settings[{{ $setting->key }}]" rows="6" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder='{"active":"فعال"}'>{{ $displayValue }}</textarea>
                                @elseif($setting->type === 'boolean')
                                    <select name="settings[{{ $setting->key }}]" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                                        <option value="1" @selected((bool) $value)>{{ __('system.statuses.enabled') }}</option>
                                        <option value="0" @selected(! (bool) $value)>{{ __('system.statuses.disabled') }}</option>
                                    </select>
                                @elseif($setting->type === 'integer')
                                    <input name="settings[{{ $setting->key }}]" value="{{ $displayValue }}" type="number" min="1" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="15">
                                @else
                                    <input name="settings[{{ $setting->key }}]" value="{{ $displayValue }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500" placeholder="{{ $setting->label }}">
                                @endif
                                <span class="mt-1 block text-xs text-zinc-500">{{ $setting->description }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </form>
@endsection
