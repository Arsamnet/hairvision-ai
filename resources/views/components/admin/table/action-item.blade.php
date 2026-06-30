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
    <a href="{{ $href }}" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" data-table-action-item>
        <x-admin.icon :name="$icon" class="h-4 w-4" />
        <span>{{ $slot }}</span>
    </a>
@else
    <form method="POST" action="{{ $action }}" @if($confirm) data-confirm-form data-confirm-message="{{ $confirm }}" @endif>
        @csrf
        @unless(strtoupper($method) === 'POST')
            @method($method)
        @endunless
        @foreach($inputs as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold transition {{ $classes }}" data-table-action-item>
            <x-admin.icon :name="$icon" class="h-4 w-4" />
            <span>{{ $slot }}</span>
        </button>
    </form>
@endif
