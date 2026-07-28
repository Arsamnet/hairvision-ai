@php
    $navigationClass = $navigationClass ?? 'admin-menu-scroll min-h-0 flex-1 space-y-1 overflow-y-auto p-3 text-sm font-bold';
    $closeOnNavigate = (bool) ($closeOnNavigate ?? false);
@endphp

<nav data-admin-menu-scroll class="{{ $navigationClass }}">
    @foreach($menuGroups as $group)
        @php
            $visibleItems = $visibleItemsForGroup($group);
        @endphp
        @continue($visibleItems->isEmpty())

        @if($visibleItems->count() === 1 && ! ($group['forceSubmenu'] ?? false))
            @php
                $item = $visibleItems->first();
                $locked = $itemIsLocked($item);
                $active = $itemIsActive($item);
            @endphp

            @if($locked)
                <span data-menu-item="{{ $item['route'] }}" class="{{ $menuItemClass($active, true) }}" aria-disabled="true" title="{{ __('permissions.plan_required') }}">
                    <span class="admin-menu-label">
                        <x-admin.icon :name="$item['icon']" />
                        <span>{{ $item['label'] }}</span>
                    </span>
                    <x-admin.icon name="lock" class="admin-menu-lock h-4 w-4" />
                </span>
            @else
                <a
                    data-menu-item="{{ $item['route'] }}"
                    href="{{ route($item['route']) }}"
                    class="{{ $menuItemClass($active) }}"
                    @if($active) aria-current="page" @endif
                    @if($closeOnNavigate) data-admin-mobile-menu-close @endif
                >
                    <span class="admin-menu-label">
                        <x-admin.icon :name="$item['icon']" />
                        <span>{{ $item['label'] }}</span>
                    </span>
                </a>
            @endif
        @else
            <details data-menu-group="{{ $group['label'] }}" class="admin-menu-details" @if($groupIsActive($group)) open @endif>
                <summary class="{{ $menuItemClass(false) }} admin-menu-summary">
                    <span class="admin-menu-label">
                        <x-admin.icon :name="$group['icon']" />
                        <span>{{ $group['label'] }}</span>
                    </span>
                    <x-admin.icon name="chevron-down" class="admin-menu-caret h-4 w-4" />
                </summary>
                <div class="admin-submenu">
                    @foreach($visibleItems as $item)
                        @php
                            $locked = $itemIsLocked($item);
                            $active = $itemIsActive($item);
                        @endphp

                        @if($locked)
                            <span data-menu-item="{{ $item['route'] }}" class="{{ $menuItemClass($active, true) }}" aria-disabled="true" title="{{ __('permissions.plan_required') }}">
                                <span class="admin-menu-label">
                                    <x-admin.icon :name="$item['icon']" />
                                    <span>{{ $item['label'] }}</span>
                                </span>
                                <x-admin.icon name="lock" class="admin-menu-lock h-4 w-4" />
                            </span>
                        @else
                            <a
                                data-menu-item="{{ $item['route'] }}"
                                href="{{ route($item['route']) }}"
                                class="{{ $menuItemClass($active) }}"
                                @if($active) aria-current="page" @endif
                                @if($closeOnNavigate) data-admin-mobile-menu-close @endif
                            >
                                <span class="admin-menu-label">
                                    <x-admin.icon :name="$item['icon']" />
                                    <span>{{ $item['label'] }}</span>
                                </span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</nav>
