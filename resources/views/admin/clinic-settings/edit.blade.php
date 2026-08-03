{{-- @since 2.1.0 Introduces the tenant-scoped clinic landing-link settings section. --}}
@extends('admin.layout')

@section('title', __('clinic_settings.title'))
@section('page_title', __('clinic_settings.title'))

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                    <x-admin.icon name="settings" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-lg font-bold">{{ __('clinic_settings.page_intro_title') }}</h2>
                    <p class="mt-1 text-sm leading-7 text-zinc-500">{{ __('clinic_settings.page_hint') }}</p>
                </div>
            </div>
        </x-admin.card>

        <div class="space-y-5" data-clinic-settings-sections>
            <form method="POST" action="{{ route('admin.clinic.settings.landing-link.update') }}" data-settings-section="landing-link">
                @csrf
                @method('PUT')

                <section id="landing-link" class="scroll-mt-5 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-zinc-200" aria-labelledby="landing-link-title">
                    <header class="border-b border-zinc-200 px-5 py-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-700 ring-1 ring-violet-100">
                                <x-admin.icon name="link" class="h-4 w-4" />
                            </span>
                            <div>
                                <h2 id="landing-link-title" class="font-bold">{{ __('clinic_settings.landing_link_title') }}</h2>
                                <p class="mt-1 text-sm leading-7 text-zinc-500">{{ __('clinic_settings.landing_link_hint') }}</p>
                            </div>
                        </div>
                    </header>

                    <div class="grid gap-5 px-5 py-5 lg:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold text-zinc-600">{{ __('clinic_settings.fields.back_link_title') }}</span>
                            <input
                                name="back_link_title"
                                value="{{ old('back_link_title', $landingSettings['back_link_title']) }}"
                                maxlength="150"
                                required
                                class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500"
                                placeholder="{{ __('clinic_settings.placeholders.back_link_title') }}"
                            >
                            @error('back_link_title')
                                <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold text-zinc-600">{{ __('clinic_settings.fields.back_link_target') }}</span>
                            <select name="back_link_target" required class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                                @foreach(__('clinic_settings.target_options') as $value => $label)
                                    <option value="{{ $value }}" @selected(old('back_link_target', $landingSettings['back_link_target']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('back_link_target')
                                <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block lg:col-span-2">
                            <span class="text-sm font-bold text-zinc-600">{{ __('clinic_settings.fields.back_link_url') }}</span>
                            <input
                                name="back_link_url"
                                type="url"
                                dir="ltr"
                                value="{{ old('back_link_url', $landingSettings['back_link_url']) }}"
                                maxlength="2048"
                                required
                                class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 py-2 text-left text-sm outline-none focus:border-teal-500"
                                placeholder="https://clinic.example"
                            >
                            <span class="mt-1 block text-xs text-zinc-500">
                                {{ __('clinic_settings.url_hint_before') }}
                                <bdi dir="ltr">http://</bdi>
                                {{ __('clinic_settings.url_hint_or') }}
                                <bdi dir="ltr">https://</bdi>
                                {{ __('clinic_settings.url_hint_after') }}
                            </span>
                            @error('back_link_url')
                                <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <footer class="flex justify-end border-t border-zinc-200 bg-zinc-50/70 px-5 py-4">
                        <x-admin.button type="submit" icon="check">{{ __('clinic_settings.save_landing_link') }}</x-admin.button>
                    </footer>
                </section>
            </form>
        </div>
    </div>
@endsection
