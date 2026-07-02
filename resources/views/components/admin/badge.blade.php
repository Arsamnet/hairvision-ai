@props(['tone' => 'zinc'])

@php
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'rose' => 'bg-rose-50 text-rose-700',
        'teal' => 'bg-teal-50 text-teal-700',
        'amber' => 'bg-amber-50 text-amber-800',
        'zinc' => 'bg-zinc-100 text-zinc-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-3 py-1 text-xs font-bold text-nowrap '.($tones[$tone] ?? $tones['zinc'])]) }}>
    {{ $slot }}
</span>
