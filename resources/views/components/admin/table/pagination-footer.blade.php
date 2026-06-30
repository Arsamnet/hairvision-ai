@props([
    'paginator',
])

@php
    $hasPages = $paginator->lastPage() > 1;
    $from = $paginator->firstItem() ?? 0;
    $to = $paginator->lastItem() ?? 0;
    $total = $paginator->total();
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $windowStart = max(1, $currentPage - 2);
    $windowEnd = min($lastPage, $currentPage + 2);
    $pageButtonClass = 'grid h-8 min-w-8 place-items-center rounded-lg border px-2 text-xs font-bold transition';
    $enabledButtonClass = $pageButtonClass.' border-zinc-200 bg-white text-zinc-600 hover:border-teal-300 hover:text-teal-700';
    $activeButtonClass = $pageButtonClass.' border-teal-600 bg-teal-600 text-white';
    $disabledButtonClass = $pageButtonClass.' border-zinc-200 bg-white text-zinc-400 opacity-45';
@endphp

<footer {{ $attributes->merge(['class' => 'mt-0 flex flex-col gap-3 border-t border-zinc-100 bg-zinc-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    <p class="text-xs font-bold text-zinc-500">
        {{ __('messages.pagination_summary', ['from' => number_format($from), 'to' => number_format($to), 'total' => number_format($total)]) }}
    </p>

    @if($hasPages)
        <nav class="inline-flex items-center gap-1" aria-label="{{ __('messages.pagination') }}">
            @if($paginator->previousPageUrl())
                <a href="{{ $paginator->previousPageUrl() }}" class="{{ $enabledButtonClass }}" aria-label="{{ __('messages.previous_page') }}">
                    <x-admin.icon name="chevron-right" />
                </a>
            @else
                <span class="{{ $disabledButtonClass }}" aria-disabled="true" aria-label="{{ __('messages.previous_page') }}">
                    <x-admin.icon name="chevron-right" />
                </span>
            @endif

            @if($windowStart > 1)
                <a href="{{ $paginator->url(1) }}" class="{{ $enabledButtonClass }}">1</a>
                @if($windowStart > 2)
                    <span class="grid h-8 min-w-8 place-items-center px-2 text-xs font-bold text-zinc-400">...</span>
                @endif
            @endif

            @for($page = $windowStart; $page <= $windowEnd; $page++)
                @if($page === $currentPage)
                    <span class="{{ $activeButtonClass }}" aria-current="page">{{ number_format($page) }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="{{ $enabledButtonClass }}">{{ number_format($page) }}</a>
                @endif
            @endfor

            @if($windowEnd < $lastPage)
                @if($windowEnd < $lastPage - 1)
                    <span class="grid h-8 min-w-8 place-items-center px-2 text-xs font-bold text-zinc-400">...</span>
                @endif
                <a href="{{ $paginator->url($lastPage) }}" class="{{ $enabledButtonClass }}">{{ number_format($lastPage) }}</a>
            @endif

            @if($paginator->nextPageUrl())
                <a href="{{ $paginator->nextPageUrl() }}" class="{{ $enabledButtonClass }}" aria-label="{{ __('messages.next_page') }}">
                    <x-admin.icon name="chevron-left" />
                </a>
            @else
                <span class="{{ $disabledButtonClass }}" aria-disabled="true" aria-label="{{ __('messages.next_page') }}">
                    <x-admin.icon name="chevron-left" />
                </span>
            @endif
        </nav>
    @else
        <div class="inline-flex items-center gap-1 opacity-45" aria-disabled="true">
            <span class="grid h-8 min-w-8 place-items-center rounded-lg border border-zinc-200 bg-white text-xs font-bold text-zinc-400">
                <x-admin.icon name="chevron-right" />
            </span>
            <span class="grid h-8 min-w-8 place-items-center rounded-lg border border-zinc-200 bg-white px-3 text-xs font-bold text-zinc-400">1</span>
            <span class="grid h-8 min-w-8 place-items-center rounded-lg border border-zinc-200 bg-white text-xs font-bold text-zinc-400">
                <x-admin.icon name="chevron-left" />
            </span>
        </div>
    @endif
</footer>
