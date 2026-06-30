@props(['name'])

@php
    $class = (string) $attributes->get('class');
    $sizeClass = preg_match('/\b(?:h|w|size)-/', $class) ? '' : ' h-5 w-5';

    $svgIcons = [
        'moon' => '<path d="M20.4 14.7A7.8 7.8 0 0 1 9.3 3.6a8.8 8.8 0 1 0 11.1 11.1Z" />',
        'sun' => '<circle cx="12" cy="12" r="3.8" /><path d="M12 2.5v2M12 19.5v2M4.6 4.6 6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4" />',
    ];

    $icons = [
        'layout-dashboard' => 'dashboard-panel',
        'users' => 'users',
        'shield' => 'shield',
        'building' => 'building',
        'contact' => 'address-book',
        'credit-card' => 'credit-card',
        'message' => 'message-sms',
        'chart' => 'chart-histogram',
        'settings' => 'settings-sliders',
        'search' => 'search',
        'filter' => 'filter',
        'bookmark' => 'bookmark',
        'lock' => 'lock',
        'sparkles' => 'sparkles',
        'more-horizontal' => 'circle-ellipsis',
        'eye' => 'eye',
        'edit' => 'edit',
        'trash' => 'trash',
        'x' => 'x',
        'chevron-down' => 'angle-small-down',
        'plus' => 'plus',
        'check' => 'check',
        'upload' => 'upload',
        'image' => 'add-image',
        'mobile' => 'mobile-button',
        'otp' => 'otp',
        'gallery' => 'images',
        'zoom-in' => 'zoom-in',
        'login' => 'arrow-right-to-bracket',
        'invoice' => 'file-invoice',
    ];

    $icon = $icons[$name] ?? 'plus';
@endphp

@if(array_key_exists($name, $svgIcons))
    <svg {{ $attributes->merge([
        'class' => 'admin-svg-icon'.$sizeClass.' shrink-0',
        'data-menu-icon' => $name,
        'aria-hidden' => 'true',
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.8',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
    ]) }}>{!! $svgIcons[$name] !!}</svg>
@else
    <i {{ $attributes->merge([
        'class' => 'fi fi-rr-'.$icon.$sizeClass.' shrink-0',
        'data-menu-icon' => $name,
        'aria-hidden' => 'true',
    ]) }}></i>
@endif
