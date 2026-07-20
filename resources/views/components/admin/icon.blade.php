@props(['name'])

@php
    $class = (string) $attributes->get('class');
    $sizeClass = preg_match('/\b(?:h|w|size)-/', $class) ? '' : ' h-4 w-4';
    $icon = \App\Support\AdminIcon::resolve($name);
@endphp

<i {{ $attributes->merge([
    'class' => 'fi fi-rr-'.$icon.$sizeClass.' shrink-0',
    'data-menu-icon' => $name,
    'aria-hidden' => 'true',
]) }}></i>
