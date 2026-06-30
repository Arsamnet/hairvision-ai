@props([
    'filterAction',
    'resolvedPresetKey',
    'savedFilters',
    'activeParameters',
    'panelOpen',
])

@php
    $modalId = 'list-filters-'.substr(md5($resolvedPresetKey), 0, 10);
    $query = request()->query();
    $searchValue = request($searchName, request('q'));
    $filterCollection = collect($filters)
        ->filter(fn ($filter) => filled($filter['name'] ?? null))
        ->values();
    $primaryFilters = $filterCollection->take(max(0, $primaryFilterCount));
    $primaryFilterNames = $primaryFilters->pluck('name')->all();
    $filterNames = $filterCollection
        ->pluck('name')
        ->values()
        ->all();
    $advancedNames = array_values(array_unique(array_merge(
        $filterNames,
        $showDates ? ['created_from', 'created_until'] : [],
        $showSort ? ['sort', 'direction'] : [],
        $showPerPage ? ['per_page'] : [],
    )));
    $advancedActive = collect($advancedNames)->filter(fn ($name) => filled(request($name)));
    $activeAdvancedCount = $advancedActive->count();
    $advancedActiveNames = $advancedActive->values();
    $advancedButtonLabel = $activeAdvancedCount > 0 ? __('messages.edit_filters') : __('messages.advanced_filters');
    $quickHidden = collect($query)
        ->except(array_merge([$searchName, 'q', 'page'], $primaryFilterNames))
        ->filter(fn ($value) => is_scalar($value) && filled($value));
    $clearSearchQuery = collect($query)->except([$searchName, 'q', 'page'])->all();
    $clearAdvancedQuery = collect($query)->except(array_merge($advancedNames, ['page']))->all();
    $clearAllQuery = collect($query)->except(array_merge($advancedNames, [$searchName, 'q', 'page']))->all();
    $queryUrl = fn (array $parameters): string => $filterAction.(count($parameters) ? '?'.http_build_query($parameters) : '');
    $perPageOptions = [10, 15, 25, 50, 100];
    $directionOptions = ['desc' => __('messages.sort_desc'), 'asc' => __('messages.sort_asc')];
    $filterLabel = function (string $name) use ($filterCollection): string {
        if ($name === 'created_from') {
            return __('messages.created_from');
        }

        if ($name === 'created_until') {
            return __('messages.created_until');
        }

        if ($name === 'sort') {
            return __('messages.sort_by');
        }

        if ($name === 'direction') {
            return __('messages.sort_direction');
        }

        if ($name === 'per_page') {
            return __('messages.per_page');
        }

        return (string) data_get($filterCollection->firstWhere('name', $name), 'label', $name);
    };
    $filterValue = function (string $name, mixed $value) use ($filterCollection, $sorts, $directionOptions): string {
        if ($name === 'sort') {
            return (string) ($sorts[$value] ?? $value);
        }

        if ($name === 'direction') {
            return (string) ($directionOptions[$value] ?? $value);
        }

        $filter = $filterCollection->firstWhere('name', $name);
        $options = data_get($filter, 'options', []);

        return (string) ($options[$value] ?? $value);
    };
@endphp

<div {{ $attributes->merge(['class' => 'mt-5 space-y-3']) }}>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ $filterAction }}" class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row">
            @foreach($quickHidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            @if($canSearch)
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">{{ __('messages.quick_search') }}</span>
                    <x-admin.icon name="search" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        name="{{ $searchName }}"
                        value="{{ $searchValue }}"
                        placeholder="{{ $searchPlaceholder ?? __('messages.search_placeholder') }}"
                        autocomplete="off"
                        class="h-11 w-full rounded-lg border border-zinc-200 pr-10 pl-3 text-sm outline-none transition focus:border-teal-500"
                    >
                </label>
            @endif

            @foreach($primaryFilters as $filter)
                @php
                    $name = $filter['name'];
                    $type = $filter['type'] ?? 'text';
                    $label = $filter['label'] ?? $name;
                    $placeholder = $filter['placeholder'] ?? $label;
                    $value = request($name);
                    $options = $filter['options'] ?? [];
                @endphp

                @if($type === 'select')
                    <label class="min-w-0 sm:w-48">
                        <span class="sr-only">{{ $label }}</span>
                        <select name="{{ $name }}" class="h-11 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:border-teal-500">
                            <option value="">{{ $placeholder }}</option>
                            @foreach($options as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label class="min-w-0 sm:w-48">
                        <span class="sr-only">{{ $label }}</span>
                        <input
                            type="{{ in_array($type, ['date', 'number'], true) ? $type : 'text' }}"
                            name="{{ $name }}"
                            value="{{ $value }}"
                            placeholder="{{ $placeholder }}"
                            @if(($filter['dir'] ?? null) === 'ltr') dir="ltr" @endif
                            class="h-11 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none transition focus:border-teal-500"
                        >
                    </label>
                @endif
            @endforeach

            <div class="flex gap-2">
                <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                    <x-admin.icon name="search" class="h-4 w-4" />
                    <span>{{ __('messages.search') }}</span>
                </button>

                @if(filled($searchValue))
                    <a href="{{ $queryUrl($clearSearchQuery) }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-bold text-zinc-600 transition hover:border-teal-300 hover:text-teal-700">
                        <x-admin.icon name="x" />
                        <span>{{ __('messages.clear_search') }}</span>
                    </a>
                @endif
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <x-admin.modal
                :id="$modalId"
                :label="$advancedButtonLabel"
                :title="__('messages.advanced_filters')"
                icon="filter"
                :open="$panelOpen && $errors->has('name')"
                :trigger-class="$activeAdvancedCount > 0
                    ? 'inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 text-sm font-bold text-amber-800 transition hover:border-amber-300'
                    : 'inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700'"
            >
                <form method="GET" action="{{ $filterAction }}" class="grid gap-3 md:grid-cols-2">
                    @if(filled($searchValue))
                        <input type="hidden" name="{{ $searchName }}" value="{{ $searchValue }}">
                    @endif

                    @foreach($filters as $filter)
                        @php
                            $name = $filter['name'] ?? null;
                            $type = $filter['type'] ?? 'text';
                            $label = $filter['label'] ?? $name;
                            $placeholder = $filter['placeholder'] ?? $label;
                            $value = $name ? request($name) : null;
                            $options = $filter['options'] ?? [];
                        @endphp
                        @continue(! $name)

                        <label class="space-y-1 text-sm font-bold text-zinc-700">
                            <span>{{ $label }}</span>
                            @if($type === 'select')
                                <select name="{{ $name }}" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                                    <option value="">{{ $placeholder }}</option>
                                    @foreach($options as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    type="{{ in_array($type, ['date', 'number'], true) ? $type : 'text' }}"
                                    name="{{ $name }}"
                                    value="{{ $value }}"
                                    placeholder="{{ $placeholder }}"
                                    @if(($filter['dir'] ?? null) === 'ltr') dir="ltr" @endif
                                    class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500"
                                >
                            @endif
                        </label>
                    @endforeach

                    @if($showDates)
                        <label class="space-y-1 text-sm font-bold text-zinc-700">
                            <span>{{ __('messages.created_from') }}</span>
                            <input type="date" name="created_from" value="{{ request('created_from') }}" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500" dir="ltr">
                        </label>
                        <label class="space-y-1 text-sm font-bold text-zinc-700">
                            <span>{{ __('messages.created_until') }}</span>
                            <input type="date" name="created_until" value="{{ request('created_until') }}" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500" dir="ltr">
                        </label>
                    @endif

                    @if($showSort)
                        <label class="space-y-1 text-sm font-bold text-zinc-700">
                            <span>{{ __('messages.sort_by') }}</span>
                            <select name="sort" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                                @foreach($sorts as $sortValue => $sortLabel)
                                    <option value="{{ $sortValue }}" @selected(request('sort') === $sortValue)>{{ $sortLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1 text-sm font-bold text-zinc-700">
                            <span>{{ __('messages.sort_direction') }}</span>
                            <select name="direction" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                                @foreach($directionOptions as $directionValue => $directionLabel)
                                    <option value="{{ $directionValue }}" @selected(request('direction', 'desc') === $directionValue)>{{ $directionLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if($showPerPage)
                        <label class="space-y-1 text-sm font-bold text-zinc-700 md:col-span-2">
                            <span>{{ __('messages.per_page') }}</span>
                            <select name="per_page" class="h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                                @foreach($perPageOptions as $perPage)
                                    <option value="{{ $perPage }}" @selected((int) request('per_page', config('hairvision.pagination.default_per_page', 15)) === $perPage)>{{ number_format($perPage) }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-zinc-100 pt-4 md:col-span-2">
                        <a href="{{ $queryUrl($clearAllQuery) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-200 px-4 text-sm font-bold text-zinc-600 transition hover:border-teal-300 hover:text-teal-700">
                            <x-admin.icon name="x" />
                            <span>{{ __('messages.clear_filters') }}</span>
                        </a>
                        <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                            <x-admin.icon name="check" />
                            <span>{{ __('messages.apply_filters') }}</span>
                        </button>
                    </div>
                </form>

                <div class="mt-5 border-t border-zinc-100 pt-4">
                    <div class="grid gap-3 lg:grid-cols-[1fr_1.2fr]">
                        <form method="POST" action="{{ route('admin.filter-presets.store') }}" class="grid gap-2">
                            @csrf
                            <input type="hidden" name="filter_key" value="{{ $resolvedPresetKey }}">
                            @foreach($activeParameters as $name => $value)
                                <input type="hidden" name="filters[{{ $name }}]" value="{{ $value }}">
                            @endforeach
                            <label class="space-y-1 text-sm font-bold text-zinc-700">
                                <span>{{ __('messages.save_current_filter') }}</span>
                                <input name="name" placeholder="{{ __('messages.filter_name_placeholder') }}" class="h-10 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                            </label>
                            <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-200 px-4 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700">
                                <x-admin.icon name="bookmark" class="h-4 w-4" />
                                <span>{{ __('messages.save_filter') }}</span>
                            </button>
                        </form>

                        <div>
                            <p class="text-sm font-bold text-zinc-700">{{ __('messages.saved_filters') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse($savedFilters as $savedFilter)
                                    @php
                                        $savedQuery = collect($query)
                                            ->except(array_merge($advancedNames, [$searchName, 'q', 'page']))
                                            ->merge($savedFilter->filters_json ?? [])
                                            ->all();
                                    @endphp
                                    <a href="{{ $queryUrl($savedQuery) }}" class="inline-flex h-9 items-center justify-center rounded-lg bg-zinc-50 px-3 text-xs font-bold text-zinc-600 ring-1 ring-zinc-200 transition hover:text-teal-700">
                                        {{ $savedFilter->name }}
                                    </a>
                                @empty
                                    <p class="rounded-lg bg-zinc-50 px-3 py-2 text-xs font-bold text-zinc-500">{{ __('messages.no_saved_filters') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </x-admin.modal>

            @if($activeAdvancedCount > 0)
                <a href="{{ $queryUrl($clearAdvancedQuery) }}" class="grid h-11 w-11 place-items-center rounded-lg border border-amber-200 bg-amber-50 text-amber-800 transition hover:border-amber-300" aria-label="{{ __('messages.remove_filters') }}" title="{{ __('messages.remove_filters') }}">
                    <x-admin.icon name="x" />
                </a>
            @endif
        </div>
    </div>

    @if($activeAdvancedCount > 0)
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-zinc-500">{{ __('messages.active_filters') }}</span>
            @foreach($advancedActiveNames as $name)
                @php
                    $value = request($name);
                    $removeQuery = collect($query)->except([$name, 'page'])->all();

                    if ($name === 'sort') {
                        $removeQuery = collect($removeQuery)->except(['direction'])->all();
                    }
                @endphp
                <span class="inline-flex h-9 items-center overflow-hidden rounded-lg border border-zinc-200 bg-white text-xs font-bold text-zinc-700">
                    <label for="{{ $modalId }}-toggle" class="inline-flex h-full cursor-pointer items-center gap-1.5 px-3 transition hover:text-teal-700">
                        <x-admin.icon name="filter" class="h-3.5 w-3.5 text-zinc-400" />
                        <span>{{ $filterLabel($name) }}: {{ $filterValue($name, $value) }}</span>
                    </label>
                    <a href="{{ $queryUrl($removeQuery) }}" class="grid h-full w-8 place-items-center border-r border-zinc-100 text-zinc-400 transition hover:bg-rose-50 hover:text-rose-700" aria-label="{{ __('messages.remove_filters') }}">
                        <x-admin.icon name="x" />
                    </a>
                </span>
            @endforeach
        </div>
    @endif
</div>
