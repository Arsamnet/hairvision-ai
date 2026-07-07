@extends('admin.layout')

@section('title', __('system.users.details'))
@section('page_title', __('system.users.details'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ $dashboardUser->name }}</h2>
                <p class="mt-1 text-sm text-zinc-500" dir="ltr">{{ $dashboardUser->email }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $dashboardUser)
                    <x-admin.button :href="route('admin.users.edit', $dashboardUser)" size="compact">{{ __('system.edit') }}</x-admin.button>
                @endcan
                @can('delete', $dashboardUser)
                    <form method="POST" action="{{ route('admin.users.destroy', $dashboardUser) }}" data-confirm-form data-confirm-message="{{ __('messages.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-admin.button type="submit" variant="danger-outline" size="compact">{{ __('system.users.delete') }}</x-admin.button>
                    </form>
                @endcan
            </div>
        </div>

        <dl class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.mobile') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ $dashboardUser->phone_normalized ?? __('messages.none') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.role') }}</dt><dd class="mt-1 font-bold">{{ __('system.roles.'.$dashboardUser->role) }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.status') }}</dt><dd class="mt-1 font-bold">{{ __('system.statuses.'.$dashboardUser->status) }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('messages.created_at') }}</dt><dd class="mt-1 font-bold"><x-jalali-date-time :value="$dashboardUser->created_at" /></dd></div>
        </dl>
    </section>

    <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <h2 class="text-lg font-bold">{{ __('system.users.clinic_access') }}</h2>
        <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-[36rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('system.clinic') }}</th>
                        <th class="px-4 py-3">{{ __('system.clinic_role') }}</th>
                        <th class="px-4 py-3">{{ __('system.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($dashboardUser->tenants as $userTenant)
                        <tr>
                            <td class="px-4 py-3 font-bold">{{ $userTenant->name }}</td>
                            <td class="px-4 py-3">{{ __('system.roles.'.$userTenant->pivot->role) }}</td>
                            <td class="px-4 py-3">{{ __('system.statuses.'.$userTenant->pivot->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-zinc-500">{{ __('system.users.no_clinics') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($dashboardUser->tenants as $userTenant)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $userTenant->name }}</p>
                            <p class="admin-mobile-list-card-subtitle">{{ __('system.roles.'.$userTenant->pivot->role) }}</p>
                        </div>
                        <x-admin.badge :tone="$userTenant->pivot->status === 'active' ? 'emerald' : 'zinc'">{{ __('system.statuses.'.$userTenant->pivot->status) }}</x-admin.badge>
                    </div>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="users">{{ __('system.users.no_clinics') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>
    </section>
@endsection
