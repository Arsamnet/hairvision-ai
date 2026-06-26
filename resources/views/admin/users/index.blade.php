@extends('admin.layout')

@section('title', __('system.users_title'))
@section('page_title', __('system.users_title'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('system.panel_users') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $tenant?->name ?? __('dashboard.platform_overview') }}</p>
            </div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3 font-bold">{{ __('system.name') }}</th>
                        <th class="py-3 font-bold">{{ __('system.mobile') }}</th>
                        <th class="py-3 font-bold">{{ __('system.role') }}</th>
                        <th class="py-3 font-bold">{{ __('system.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($users as $dashboardUser)
                        <tr>
                            <td class="py-3 font-bold">{{ $dashboardUser->name }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->phone_normalized ?? __('messages.none') }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->pivot?->role ?? $dashboardUser->role }}</td>
                            <td class="py-3 text-zinc-600">{{ $dashboardUser->pivot?->status ?? $dashboardUser->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-zinc-500">{{ __('system.empty_users') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $users->links() }}</div>
    </section>
@endsection
