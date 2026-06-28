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
                    <form method="POST" action="{{ route('admin.system.roles.store') }}" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_form" value="create_role">
                        <x-admin.validation-summary for="create_role" class="md:col-span-2" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                        <input name="code" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="{{ __('system.code') }}">
                        <input name="description" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('system.description') }}">
                        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700 md:col-span-2">
                            <x-admin.icon name="shield" class="h-4 w-4" />
                            <span>{{ __('system.submit') }}</span>
                        </button>
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[1fr_10rem_8rem]">
            <input name="search" value="{{ request('search') }}" placeholder="{{ __('system.roles_search_placeholder') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
            <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                <option value="">{{ __('system.users.all_statuses') }}</option>
                @foreach(['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('system.statuses.'.$status) }}</option>
                @endforeach
            </select>
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                <x-admin.icon name="settings" class="h-4 w-4" />
                <span>{{ __('sms.filter') }}</span>
            </button>
        </form>

        <div class="mt-5 overflow-x-auto rounded-lg border border-zinc-100">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3">{{ __('system.name') }}</th>
                        <th class="px-4 py-3">{{ __('system.code') }}</th>
                        <th class="px-4 py-3">{{ __('system.permissions') }}</th>
                        <th class="px-4 py-3">{{ __('system.status') }}</th>
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
                            <td class="px-4 py-3 text-zinc-600">{{ number_format($role->permissions_count) }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$role->status === 'active' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$role->status) }}</x-admin.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12">
                                <x-admin.empty-state icon="shield">{{ __('system.empty_roles') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $roles->links() }}</div>
    </x-admin.card>
@endsection
