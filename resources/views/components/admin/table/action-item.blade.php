@props([
    'href' => null,
    'action' => null,
    'method' => 'GET',
    'icon' => 'eye',
    'danger' => false,
    'confirm' => null,
])

@php
    $classes = $danger
        ? 'text-rose-700 hover:bg-rose-50'
        : 'text-zinc-700 hover:bg-teal-50 hover:text-teal-700';
@endphp

@if($href)
    <a href="{{ $href }}" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}">
        <x-admin.icon :name="$icon" class="h-4 w-4" />
        <span>{{ $slot }}</span>
    </a>
@else
    <form method="POST" action="{{ $action }}">
        @csrf
        @unless(strtoupper($method) === 'POST')
            @method($method)
        @endunless
        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" @if($confirm) onclick="return confirm(@js($confirm))" @endif>
            <x-admin.icon :name="$icon" class="h-4 w-4" />
            <span>{{ $slot }}</span>
        </button>
    </form>
@endif
