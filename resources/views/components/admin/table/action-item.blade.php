{{-- @since 2.1.0 Supports explicitly disabled policy-backed row actions. --}}
@props([
    'href' => null,
    'action' => null,
    'method' => 'GET',
    'icon' => 'eye',
    'danger' => false,
    'disabled' => false,
    'confirm' => null,
    'inputs' => [],
])

@php
    $classes = $disabled
        ? 'cursor-not-allowed text-zinc-400 opacity-60'
        : ($danger
            ? 'text-rose-700 hover:bg-rose-50'
            : 'text-zinc-700 hover:bg-teal-50 hover:text-teal-700');
    $isRowViewAction = $href && $icon === 'eye';
@endphp

@if($disabled)
    <x-admin.button type="button" variant="unstyled" size="none" :icon="$icon" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" disabled aria-disabled="true" data-table-action-item>
        <span>{{ $slot }}</span>
    </x-admin.button>
@elseif($href)
    @if($isRowViewAction)
        <x-admin.button :href="$href" variant="unstyled" size="none" :icon="$icon" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" data-table-action-item data-table-row-view-action>
            <span>{{ $slot }}</span>
        </x-admin.button>
    @else
        <x-admin.button :href="$href" variant="unstyled" size="none" :icon="$icon" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" data-table-action-item>
            <span>{{ $slot }}</span>
        </x-admin.button>
    @endif
@else
    <form method="POST" action="{{ $action }}" @if($confirm) data-confirm-form data-confirm-message="{{ $confirm }}" @endif>
        @csrf
        @unless(strtoupper($method) === 'POST')
            @method($method)
        @endunless
        @foreach($inputs as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <x-admin.button type="submit" variant="unstyled" size="none" :icon="$icon" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" data-table-action-item>
            <span>{{ $slot }}</span>
        </x-admin.button>
    </form>
@endif
