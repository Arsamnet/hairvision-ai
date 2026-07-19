@props(['category' => null, 'icon' => null])

@php
    $iconCode = $category instanceof \App\Models\AiModelCategory
        ? $category->resolvedIcon()
        : \App\Support\AdminIcon::normalize($icon, \App\Support\AdminIcon::DEFAULT_CATEGORY_ICON);

    $class = (string) $attributes->get('class');
    $sizeClass = preg_match('/\b(?:h|w|size)-/', $class) ? '' : ' h-4 w-4';
@endphp

<i {{ $attributes->merge([
    'class' => 'fi fi-rr-'.$iconCode.$sizeClass.' shrink-0',
    'aria-hidden' => 'true',
    'data-category-icon' => $iconCode,
]) }}></i>
