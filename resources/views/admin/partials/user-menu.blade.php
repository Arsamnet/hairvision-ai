@php
    $triggerClass = $triggerClass ?? 'admin-user-menu-trigger';
    $menuClass = $menuClass ?? 'admin-user-menu';
    $avatarSrc = $avatarSrc ?? '/theme/admin-user-avatar-neutral.svg';
@endphp

<details class="{{ $menuClass }}" data-admin-user-menu>
    <summary class="{{ $triggerClass }}" aria-label="{{ __('dashboard.user_menu.open') }}" title="{{ __('dashboard.user_menu.open') }}">
        <img src="{{ $avatarSrc }}" alt="" class="admin-user-menu-trigger-avatar" aria-hidden="true">
    </summary>
    <div class="admin-user-menu-panel">
        <div class="admin-user-menu-header">
            <span class="admin-user-avatar">
                <img src="{{ $avatarSrc }}" alt="" class="admin-user-avatar-image" aria-hidden="true">
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold">{{ $user?->name ?? __('dashboard.user_menu.account') }}</p>
                <p class="mt-1 truncate text-xs">{{ $user?->role ? __('system.roles.'.$user->role) : __('dashboard.user_menu.account') }}</p>
            </div>
        </div>

        <x-admin.button type="button" variant="unstyled" size="none" class="admin-user-menu-item" data-theme-toggle data-light-label="{{ __('dashboard.theme.light') }}" data-dark-label="{{ __('dashboard.theme.dark') }}" aria-label="{{ __('dashboard.theme.toggle') }}" title="{{ __('dashboard.theme.toggle') }}">
            <span class="admin-user-menu-item-label">
                <x-admin.icon name="moon" class="theme-icon-moon h-4 w-4" />
                <x-admin.icon name="sun" class="theme-icon-sun h-4 w-4" />
                <span>{{ __('dashboard.theme.menu_item') }}</span>
            </span>
        </x-admin.button>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <x-admin.button type="submit" variant="unstyled" size="none" class="admin-user-menu-item is-danger">
                <span class="admin-user-menu-item-label">
                    <x-admin.icon name="lock" class="h-4 w-4" />
                    <span>{{ __('dashboard.logout') }}</span>
                </span>
            </x-admin.button>
        </form>
    </div>
</details>
