@props(['icon' => 'sparkles'])

<div data-admin-empty-state {{ $attributes->merge(['class' => 'mx-auto max-w-sm text-center']) }}>
    <span class="mx-auto grid h-12 w-12 place-items-center rounded-lg bg-zinc-50 text-zinc-400">
        <x-admin.icon :name="$icon" />
    </span>
    <p class="mt-3 text-sm font-bold text-zinc-600">{{ $slot }}</p>
</div>
