@extends('admin.layout')

@section('title', __('system.access_title'))
@section('page_title', __('system.access_title'))

@section('content')
    <x-admin.card>
        <form method="GET" class="mb-5 grid gap-3 lg:grid-cols-[1fr_11rem_11rem_8rem]">
            <input name="search" value="{{ request('search') }}" placeholder="{{ __('system.users.search_placeholder') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
            <select name="role" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                <option value="">{{ __('system.users.all_roles') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role->code }}" @selected(request('role') === $role->code)>{{ __('system.roles.'.$role->code) }}</option>
                @endforeach
            </select>
            <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                <option value="">{{ __('system.users.all_statuses') }}</option>
                @foreach(['active', 'suspended', 'disabled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('system.statuses.'.$status) }}</option>
                @endforeach
            </select>
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                <x-admin.icon name="settings" class="h-4 w-4" />
                <span>{{ __('system.users.filter') }}</span>
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[60rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 py-3">{{ __('messages.actions') }}</th>
                        <th class="py-3">{{ __('system.users_title') }}</th>
                        <th class="py-3">{{ __('system.global_role') }}</th>
                        <th class="py-3">{{ __('system.clinic') }}</th>
                        <th class="py-3">{{ __('system.clinic_role') }}</th>
                        <th class="py-3">{{ __('system.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($users as $dashboardUser)
                        @php($formId = 'access-form-'.$dashboardUser->id)
                        <tr>
                            <td class="py-3 align-top">
                                <x-admin.table.action-menu>
                                    <button type="submit" form="{{ $formId }}" class="flex w-full items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700">
                                        <x-admin.icon name="settings" class="h-4 w-4" />
                                        <span>{{ __('system.save') }}</span>
                                    </button>
                                </x-admin.table.action-menu>
                            </td>
                            <td class="py-3 font-bold">{{ $dashboardUser->name }}<p class="text-xs text-zinc-500">{{ $dashboardUser->phone_normalized }}</p></td>
                            <td class="py-3">
                                <select form="{{ $formId }}" name="role" class="rounded-lg border border-zinc-200 px-2 py-2">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->code }}" @selected($dashboardUser->role === $role->code)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-3">
                                <select form="{{ $formId }}" name="tenant_id" class="rounded-lg border border-zinc-200 px-2 py-2">
                                    <option value="">{{ __('messages.none') }}</option>
                                    @foreach($tenants as $tenantOption)
                                        <option value="{{ $tenantOption->id }}">{{ $tenantOption->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-3">
                                <select form="{{ $formId }}" name="tenant_role" class="rounded-lg border border-zinc-200 px-2 py-2">
                                    <option value="">{{ __('messages.none') }}</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->code }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-3">
                                <select form="{{ $formId }}" name="status" class="rounded-lg border border-zinc-200 px-2 py-2">
                                    @foreach(['active', 'suspended', 'disabled'] as $status)
                                        <option value="{{ $status }}" @selected($dashboardUser->status === $status)>{{ __('system.statuses.'.$status) }}</option>
                                    @endforeach
                                </select>
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

        @foreach($users as $dashboardUser)
            <form id="access-form-{{ $dashboardUser->id }}" method="POST" action="{{ route('admin.system.access.update', $dashboardUser) }}" class="hidden">
                @csrf
                @method('PUT')
                <input type="hidden" name="tenant_status" value="active">
            </form>
        @endforeach
    </x-admin.card>
@endsection
