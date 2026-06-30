@props(['name'])

@php
    $class = (string) $attributes->get('class');
    $sizeClass = preg_match('/\b(?:h|w|size)-/', $class) ? '' : ' h-4 w-4';

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
        'package' => 'box-open',
        'Z' => 'sparkles',
        'menu-dots-vertical' => 'menu-dots-vertical',
        'eye' => 'eye',
        'edit' => 'edit',
        'trash' => 'trash',
        'x' => 'cross-small',
        'chevron-down' => 'angle-small-down',
        'chevron-left' => 'angle-small-left',
        'chevron-right' => 'angle-small-right',
        'chevrons-up-down' => 'sort-alt',
        'arrow-up' => 'arrow-up',
        'arrow-down' => 'arrow-down',
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
        'night-day' => 'night-day',
        'moon' => 'night-day',
        'sun' => 'night-day',
    ];

    $icon = $icons[$name] ?? 'plus';
@endphp

<i {{ $attributes->merge([
    'class' => 'fi fi-rr-'.$icon.$sizeClass.' shrink-0',
    'data-menu-icon' => $name,
    'aria-hidden' => 'true',
]) }}></i>
