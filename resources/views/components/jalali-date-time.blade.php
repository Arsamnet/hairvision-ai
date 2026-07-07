@props(['value' => null, 'empty' => null])

@php
    $formatted = \App\Support\JalaliDate::toJalaliDateTime($value);
@endphp

<time @if($formatted) datetime="{{ $formatted }}" @endif {{ $attributes->merge(['dir' => 'ltr']) }}>{{ $formatted ?? ($empty ?? __('messages.none')) }}</time>
