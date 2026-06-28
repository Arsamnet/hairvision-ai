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
                    <a href="{{ route('admin.users.edit', $dashboardUser) }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white transition hover:bg-teal-700">{{ __('system.edit') }}</a>
                @endcan
                @can('delete', $dashboardUser)
                    <form method="POST" action="{{ route('admin.users.destroy', $dashboardUser) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-50">{{ __('system.users.delete') }}</button>
                    </form>
                @endcan
            </div>
        </div>

        <dl class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.mobile') }}</dt><dd class="mt-1 font-bold" dir="ltr">{{ $dashboardUser->phone_normalized ?? __('messages.none') }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.role') }}</dt><dd class="mt-1 font-bold">{{ __('system.roles.'.$dashboardUser->role) }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('system.status') }}</dt><dd class="mt-1 font-bold">{{ __('system.statuses.'.$dashboardUser->status) }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-sm text-zinc-500">{{ __('messages.created_at') }}</dt><dd class="mt-1 font-bold">{{ $dashboardUser->created_at?->diffForHumans() }}</dd></div>
        </dl>
    </section>

    <section class="mt-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <h2 class="text-lg font-bold">{{ __('system.users.clinic_access') }}</h2>
        <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-100">
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
    </section>
@endsection
