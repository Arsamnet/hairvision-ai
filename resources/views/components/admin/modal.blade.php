@props([
    'id',
    'label',
    'title' => null,
    'icon' => 'sparkles',
    'open' => false,
    'trigger' => true,
    'triggerClass' => 'inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700',
])

@php
    $toggleId = $id.'-toggle';
@endphp

<div @class(['inline-block' => $trigger, 'contents' => ! $trigger])>
    <input id="{{ $toggleId }}" type="checkbox" class="peer sr-only" data-admin-modal-toggle="{{ $id }}" @checked($open)>
    @if($trigger)
        <label for="{{ $toggleId }}" class="{{ $triggerClass }}" role="button" tabindex="0" data-admin-modal-trigger="{{ $id }}">
            <x-admin.icon :name="$icon" class="h-4 w-4" />
            <span>{{ $label }}</span>
        </label>
    @endif

    <div class="fixed inset-0 z-50 hidden items-center justify-center p-4 peer-checked:flex" data-admin-modal="{{ $id }}">
        @if($trigger)
            <label for="{{ $toggleId }}" class="absolute inset-0 cursor-pointer bg-zinc-950/45" aria-label="{{ __('messages.close') }}" data-admin-modal-backdrop="{{ $id }}"></label>
        @else
            <x-admin.button type="button" variant="unstyled" size="none" class="absolute inset-0 bg-zinc-950/45" aria-label="{{ __('messages.close') }}" data-admin-modal-backdrop="{{ $id }}" />
        @endif
        <section class="relative z-10 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl ring-1 ring-zinc-200" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
            <div class="flex items-center justify-between gap-3 border-b border-zinc-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-teal-50 text-teal-700">
                        <x-admin.icon :name="$icon" class="h-4 w-4" />
                    </span>
                    <h2 id="{{ $id }}-title" class="text-base font-bold">{{ $title ?? $label }}</h2>
                </div>
                @if($trigger)
                    <label for="{{ $toggleId }}" class="grid h-9 w-9 cursor-pointer place-items-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-950" role="button" tabindex="0" aria-label="{{ __('messages.close') }}" data-admin-modal-close="{{ $id }}" data-admin-modal-label-close>
                        <x-admin.icon name="x" class="h-4 w-4" />
                    </label>
                @else
                    <x-admin.button type="button" variant="unstyled" size="none" icon="x" class="grid h-9 w-9 place-items-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-950" aria-label="{{ __('messages.close') }}" data-admin-modal-close="{{ $id }}" />
                @endif
            </div>

            <div class="mt-4">
                {{ $slot }}
            </div>
        </section>
    </div>
</div>
