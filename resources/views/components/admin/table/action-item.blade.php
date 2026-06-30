@props([
    'href' => null,
    'action' => null,
    'method' => 'GET',
    'icon' => 'eye',
    'danger' => false,
    'confirm' => null,
    'inputs' => [],
])

@php
    $classes = $danger
        ? 'text-rose-700 hover:bg-rose-50'
        : 'text-zinc-700 hover:bg-teal-50 hover:text-teal-700';
@endphp

@if($href)
    <x-admin.button :href="$href" variant="unstyled" size="none" :icon="$icon" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" data-table-action-item>
        <span>{{ $slot }}</span>
    </x-admin.button>
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
