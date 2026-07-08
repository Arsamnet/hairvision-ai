@extends('admin.layout')

@section('title', __('system.users_title'))
@section('page_title', __('system.users_title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('system.panel_users') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $clinicFilter?->name ?? __('dashboard.all_clinics') }}</p>
            </div>
            @can('create', App\Models\User::class)
                <x-admin.modal id="create-user-modal" :label="__('system.users.create')" :title="__('system.users.create')" icon="users" :open="$errors->any() && old('_form') === 'create_user'">
                    <form method="POST" action="{{ route('admin.users.store') }}">
                        @csrf
                        @include('admin.users.form', ['dashboardUser' => null, 'formKey' => 'create_user', 'returnToIndex' => true])
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        @php
            $isSystemPanel = auth()->user()?->isSuperAdmin() && blank($tenant ?? null);
            $statusOptions = collect(['active', 'inactive'])->mapWithKeys(fn ($status) => [$status => __('system.statuses.'.$status)])->all();
            $userFilters = [];

            if ($isSystemPanel && ($availableTenants?->count() ?? 0) > 1) {
                $userFilters[] = ['name' => 'clinic_id', 'label' => __('system.clinic'), 'type' => 'select', 'placeholder' => __('dashboard.all_clinics'), 'options' => $availableTenants->pluck('name', 'id')->all()];
            }

            $userFilters[] = ['name' => 'status', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('system.users.all_statuses'), 'options' => $statusOptions];

            if ($isSystemPanel) {
                $userFilters[] = ['name' => 'access_scope', 'label' => __('system.access_scope'), 'type' => 'select', 'placeholder' => __('system.all_scopes'), 'options' => collect(['system', 'clinic'])->mapWithKeys(fn ($scope) => [$scope => __('system.user_access_scopes.'.$scope)])->all()];
            }

            $userFilters[] = ['name' => 'role', 'label' => __('system.role'), 'type' => 'select', 'placeholder' => __('system.users.all_roles'), 'options' => $roles->mapWithKeys(fn ($role) => [$role->code => __('system.roles.'.$role->code)])->all()];
            $primaryFilterCount = $isSystemPanel && ($availableTenants?->count() ?? 0) > 1 ? 2 : 1;
        @endphp

        <x-admin.list-filters
            preset-key="admin.users.index"
            :can-search="auth()->user()?->hasPermission('users.search', $clinicFilter?->id) ?? false"
            :search-placeholder="__('system.users.search_placeholder')"
            :filters="$userFilters"
            :primary-filter-count="$primaryFilterCount"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'name' => __('system.user_name'),
                'email' => __('system.users.email'),
                'phone_normalized' => __('system.mobile'),
                ...($isSystemPanel ? ['access_scope' => __('system.access_scope')] : []),
                'role' => __('system.role'),
                'status' => __('system.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-x-auto overflow-y-hidden rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-200 text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3 font-bold">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('system.user_name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="phone_normalized" :label="__('system.mobile')" /></th>
                        @if($isSystemPanel)
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="access_scope" :label="__('system.access_scope')" /></th>
                        @endif
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="role" :label="__('system.role')" /></th>
                        <th class="px-4 py-3 font-bold">{{ __('system.clinic') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('system.status')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($users as $dashboardUser)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('view', $dashboardUser)
                                        <x-admin.table.action-item :href="route('admin.users.show', $dashboardUser)" icon="eye">{{ __('system.users.details') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('update', $dashboardUser)
                                        <x-admin.table.action-item :href="route('admin.users.edit', $dashboardUser)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('delete', $dashboardUser)
                                        <x-admin.table.action-item :action="route('admin.users.destroy', $dashboardUser)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('system.users.delete') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold">{{ $dashboardUser->name }}</p>
                                <p class="mt-1 text-xs text-zinc-500" dir="ltr">{{ $dashboardUser->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-zinc-600" dir="ltr">{{ $dashboardUser->phone_normalized ?? __('messages.none') }}</td>
                            @if($isSystemPanel)
                                <td class="px-4 py-3">
                                    <x-admin.badge :tone="$dashboardUser->access_scope === 'system' ? 'amber' : 'teal'">{{ __('system.user_access_scopes.'.$dashboardUser->access_scope) }}</x-admin.badge>
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <x-admin.badge tone="teal">{{ __('system.roles.'.$dashboardUser->role) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">
                                @forelse($dashboardUser->tenants as $userTenant)
                                    <x-admin.badge class="mb-1">{{ $userTenant->name }}</x-admin.badge>
                                @empty
                                    {{ __('messages.none') }}
                                @endforelse
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$dashboardUser->status === 'active' ? 'emerald' : 'rose'">{{ __('system.statuses.'.$dashboardUser->status) }}</x-admin.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSystemPanel ? 7 : 6 }}" class="px-4 py-12">
                                <x-admin.empty-state icon="users">{{ __('system.empty_users') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($users as $dashboardUser)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $dashboardUser->name }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $dashboardUser->email }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('view', $dashboardUser)
                                <x-admin.table.action-item :href="route('admin.users.show', $dashboardUser)" icon="eye">{{ __('system.users.details') }}</x-admin.table.action-item>
                            @endcan
                            @can('update', $dashboardUser)
                                <x-admin.table.action-item :href="route('admin.users.edit', $dashboardUser)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                            @endcan
                            @can('delete', $dashboardUser)
                                <x-admin.table.action-item :action="route('admin.users.destroy', $dashboardUser)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('system.users.delete') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('system.mobile')">
                            <span dir="ltr">{{ $dashboardUser->phone_normalized ?? __('messages.none') }}</span>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('system.role')">
                            <x-admin.badge tone="teal">{{ __('system.roles.'.$dashboardUser->role) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        @if($isSystemPanel)
                            <x-admin.mobile-list.field :label="__('system.access_scope')">
                                <x-admin.badge :tone="$dashboardUser->access_scope === 'system' ? 'amber' : 'teal'">{{ __('system.user_access_scopes.'.$dashboardUser->access_scope) }}</x-admin.badge>
                            </x-admin.mobile-list.field>
                        @endif
                        <x-admin.mobile-list.field :label="__('system.clinic')">
                            <span class="truncate">{{ $dashboardUser->tenants->pluck('name')->join('، ') ?: __('messages.none') }}</span>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('system.status')">
                            <x-admin.badge :tone="$dashboardUser->status === 'active' ? 'emerald' : 'rose'">{{ __('system.statuses.'.$dashboardUser->status) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="users">{{ __('system.empty_users') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$users" />
    </x-admin.card>
@endsection
