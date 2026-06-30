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
            $userFilters = [
                ['name' => 'role', 'label' => __('system.role'), 'type' => 'select', 'placeholder' => __('system.users.all_roles'), 'options' => $roles->mapWithKeys(fn ($role) => [$role->code => __('system.roles.'.$role->code)])->all()],
                ['name' => 'status', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('system.users.all_statuses'), 'options' => collect(['active', 'inactive', 'blocked', 'suspended', 'disabled'])->mapWithKeys(fn ($status) => [$status => __('system.statuses.'.$status)])->all()],
            ];

            if (($availableTenants?->count() ?? 0) > 1) {
                $userFilters[] = ['name' => 'clinic_id', 'label' => __('system.clinic'), 'type' => 'select', 'placeholder' => __('dashboard.all_clinics'), 'options' => $availableTenants->pluck('name', 'id')->all()];
            }
        @endphp

        <x-admin.list-filters
            preset-key="admin.users.index"
            :can-search="auth()->user()?->hasPermission('users.search', $clinicFilter?->id) ?? false"
            :search-placeholder="__('system.users.search_placeholder')"
            :filters="$userFilters"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'name' => __('system.name'),
                'email' => __('system.users.email'),
                'phone_normalized' => __('system.mobile'),
                'role' => __('system.role'),
                'status' => __('system.status'),
            ]"
        />

        <div data-admin-table-container class="mt-5 overflow-visible rounded-lg border border-zinc-100">
            <table class="w-full min-w-[50rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3 font-bold">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('system.name') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('system.mobile') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('system.role') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('system.status') }}</th>
                        <th class="px-4 py-3 font-bold">{{ __('system.clinic') }}</th>
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
                            <td class="px-4 py-3">
                                <x-admin.badge tone="teal">{{ __('system.roles.'.$dashboardUser->role) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$dashboardUser->status === 'active' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$dashboardUser->status) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">
                                @forelse($dashboardUser->tenants as $userTenant)
                                    <x-admin.badge class="mb-1">{{ $userTenant->name }}</x-admin.badge>
                                @empty
                                    {{ __('messages.none') }}
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="users">{{ __('system.empty_users') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $users->links() }}</div>
    </x-admin.card>
@endsection
