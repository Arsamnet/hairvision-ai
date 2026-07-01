@props([
    'sort',
    'label',
])

@php
    $currentSort = request('sort');
    $currentDirection = request('direction', 'desc');
    $active = $currentSort === $sort;
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = collect(request()->query())
        ->except(['page'])
        ->merge([
            'sort' => $sort,
            'direction' => $nextDirection,
        ])
        ->all();
    $href = request()->url().'?'.http_build_query($query);
    $icon = $active
        ? ($currentDirection === 'asc' ? 'arrow-up' : 'arrow-down')
        : 'sort-alt';
@endphp

<a
    href="{{ $href }}"
    class="inline-flex items-center gap-1.5 font-bold transition {{ $active ? 'text-teal-700' : 'text-zinc-500 hover:text-teal-700' }}"
    aria-sort="{{ $active ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"
>
    <span>{{ $label }}</span>
    <x-admin.icon :name="$icon" class="h-3.5 w-3.5 {{ $active ? 'text-teal-700' : 'text-zinc-400' }}" />
</a>
