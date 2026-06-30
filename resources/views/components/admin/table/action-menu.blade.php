@props(['label' => __('messages.actions')])

<details data-table-action-menu class="relative z-10 inline-block text-right open:z-[80]">
    <summary class="grid h-9 w-9 cursor-pointer list-none place-items-center rounded-lg border border-zinc-200 bg-white text-zinc-700 transition hover:border-teal-300 hover:text-teal-700 [&::-webkit-details-marker]:hidden" aria-label="{{ $label }}" title="{{ $label }}">
        <x-admin.icon name="menu-dots-vertical" class="h-4 w-4" />
    </summary>
    <div class="absolute left-0 z-[90] mt-2 w-44 overflow-hidden rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-zinc-200 lg:left-auto lg:right-0">
        {{ $slot }}
    </div>
</details>
