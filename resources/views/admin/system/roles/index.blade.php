@extends('admin.layout')

@section('title', __('system.roles_title'))
@section('page_title', __('system.roles_title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('system.roles_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('menu.items.roles') }}</p>
            </div>
            @can('permission', 'roles.create')
                <x-admin.modal id="create-role-modal" :label="__('system.new_role')" :title="__('system.new_role')" icon="shield" :open="$errors->any() && old('_form') === 'create_role'">
                    <form method="POST" action="{{ route('admin.system.roles.store') }}" class="grid gap-3 lg:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_form" value="create_role">
                        <x-admin.validation-summary for="create_role" class="lg:col-span-2" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                        <input name="code" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="{{ __('system.code') }}">
                        <input name="description" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 lg:col-span-2" placeholder="{{ __('system.description') }}">
                        <x-admin.button type="submit" icon="shield" class="lg:col-span-2">
                            <span>{{ __('system.submit') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <x-admin.list-filters
            preset-key="admin.system.roles.index"
            :search-placeholder="__('system.roles_search_placeholder')"
            :filters="[
                ['name' => 'scope', 'label' => __('system.scope'), 'type' => 'select', 'placeholder' => __('system.all_scopes'), 'options' => collect(['platform', 'clinic'])->mapWithKeys(fn ($scope) => [$scope => __('system.role_scopes.'.$scope)])->all()],
                ['name' => 'status', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('system.users.all_statuses'), 'options' => collect(['active', 'inactive'])->mapWithKeys(fn ($status) => [$status => __('system.statuses.'.$status)])->all()],
            ]"
            :sorts="[
                'name' => __('system.name'),
                'code' => __('system.code'),
                'scope' => __('system.scope'),
                'status' => __('system.status'),
                'created_at' => __('messages.created_at'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('system.name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="code" :label="__('system.code')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="scope" :label="__('system.scope')" /></th>
                        <th class="px-4 py-3">{{ __('system.permissions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('system.status')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($roles as $role)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('permission', 'roles.view')
                                        <x-admin.table.action-item :href="route('admin.system.roles.edit', $role)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold">{{ $role->name }}</td>
                            <td class="px-4 py-3 text-zinc-600" dir="ltr">{{ $role->code }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ __('system.role_scopes.'.$role->scope) }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ number_format($role->permissions_count) }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$role->status === 'active' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$role->status) }}</x-admin.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="shield">{{ __('system.empty_roles') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($roles as $role)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $role->name }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $role->code }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('permission', 'roles.view')
                                <x-admin.table.action-item :href="route('admin.system.roles.edit', $role)" icon="edit">{{ __('system.edit') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('system.permissions')">
                            {{ number_format($role->permissions_count) }}
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('system.scope')">
                            {{ __('system.role_scopes.'.$role->scope) }}
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('system.status')">
                            <x-admin.badge :tone="$role->status === 'active' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$role->status) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="shield">{{ __('system.empty_roles') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>
        <x-admin.table.pagination-footer :paginator="$roles" />
    </x-admin.card>
@endsection
