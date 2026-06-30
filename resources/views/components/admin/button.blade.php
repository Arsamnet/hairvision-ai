@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconClass' => 'h-4 w-4',
    'iconPosition' => 'before',
    'block' => false,
])

@php
    $variantClasses = [
        'primary' => 'bg-zinc-950 text-white hover:bg-teal-700',
        'teal' => 'bg-teal-700 text-white hover:bg-zinc-950',
        'secondary' => 'border border-zinc-200 text-zinc-700 hover:border-teal-300 hover:text-teal-700',
        'secondary-muted' => 'border border-zinc-200 text-zinc-600 hover:border-teal-300 hover:text-teal-700',
        'subtle' => 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'danger-outline' => 'border border-rose-200 text-rose-700 hover:bg-rose-50',
        'success-outline' => 'border border-zinc-200 text-zinc-700 hover:border-emerald-300 hover:text-emerald-700',
        'warning-soft' => 'border border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-300',
        'saved-filter' => 'bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200 hover:text-teal-700',
        'unstyled' => '',
    ];

    $sizeClasses = [
        'xs' => 'h-9 px-3 text-xs',
        'sm' => 'h-10 px-4 text-sm',
        'md' => 'h-11 px-4 text-sm',
        'md-wide' => 'h-11 px-5 text-sm',
        'lg' => 'h-12 px-4 text-sm',
        'compact-sm' => 'px-3 py-2 text-xs',
        'compact' => 'px-4 py-2 text-sm',
        'compact-md' => 'px-5 py-2.5 text-sm',
        'icon-sm' => 'h-9 w-9',
        'icon-md' => 'h-11 w-11',
        'none' => '',
    ];

    $baseClasses = $variant === 'unstyled'
        ? 'cursor-pointer'
        : 'inline-flex items-center justify-center gap-2 rounded-lg font-bold transition focus:outline-none cursor-pointer';

    $classes = collect([
        $baseClasses,
        $sizeClasses[$size] ?? $sizeClasses['md'],
        $variantClasses[$variant] ?? $variantClasses['primary'],
        $block ? 'w-full' : null,
        $attributes->get('class'),
    ])->filter()->implode(' ');

    $attributeBag = $attributes->except('class')->merge([
        'class' => $classes,
        'data-admin-button' => true,
    ]);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributeBag }}>
        @if($icon && $iconPosition === 'before')
            <x-admin.icon :name="$icon" class="{{ $iconClass }}" />
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'after')
            <x-admin.icon :name="$icon" class="{{ $iconClass }}" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributeBag }}>
        @if($icon && $iconPosition === 'before')
            <x-admin.icon :name="$icon" class="{{ $iconClass }}" />
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'after')
            <x-admin.icon :name="$icon" class="{{ $iconClass }}" />
        @endif
    </button>
@endif
