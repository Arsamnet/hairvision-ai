@props(['name'])

@php
    $class = (string) $attributes->get('class');
    $sizeClass = preg_match('/\b(?:h|w|size)-/', $class) ? '' : ' h-4 w-4';

    $icons = [
        'layout-dashboard' => 'dashboard-panel',
        'users' => 'users',
        'shield' => 'shield',
        'building' => 'building',
        'calendar' => 'calendar',
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
        'sort-alt' => 'sort-alt',
        'arrow-up' => 'arrow-up',
        'arrow-down' => 'arrow-down',
        'plus' => 'plus',
        'check' => 'check',
        'upload' => 'upload',
        'image' => 'add-image',
        'mobile' => 'mobile-button',
        'otp' => 'otp',
        'gallery' => 'images',
        'category' => 'sparkles',
        'archive' => 'bookmark',
        'zoom-in' => 'zoom-in',
        'login' => 'arrow-right-to-bracket',
        'invoice' => 'file-invoice',
        'night-day' => 'night-day',
        'moon' => 'night-day',
        'sun' => 'night-day',
    ];

    $icon = $icons[$name] ?? 'plus';
@endphp

@if($name === 'bell')
    <svg {{ $attributes->merge([
        'class' => trim($sizeClass.' shrink-0'),
        'data-menu-icon' => $name,
        'aria-hidden' => 'true',
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
    ]) }}>
        <path d="M15 18.25a3 3 0 0 1-6 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
        <path d="M5.75 16.5h12.5l-1.15-1.85a4.2 4.2 0 0 1-.64-2.22V9.9a4.46 4.46 0 0 0-8.92 0v2.53c0 .79-.22 1.56-.64 2.22L5.75 16.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" />
        <path d="M12 3.25v1.2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
    </svg>
@elseif($name === 'link')
    <svg {{ $attributes->merge([
        'class' => trim($sizeClass.' shrink-0'),
        'data-menu-icon' => $name,
        'aria-hidden' => 'true',
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
    ]) }}>
        <path d="M9.75 14.25 14.25 9.75" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
        <path d="M10.6 7.2 12 5.8a4.1 4.1 0 0 1 5.8 5.8l-1.4 1.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M13.4 16.8 12 18.2a4.1 4.1 0 0 1-5.8-5.8l1.4-1.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
@else
    <i {{ $attributes->merge([
        'class' => 'fi fi-rr-'.$icon.$sizeClass.' shrink-0',
        'data-menu-icon' => $name,
        'aria-hidden' => 'true',
    ]) }}></i>
@endif
