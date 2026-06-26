@extends('admin.layout')

@section('title', __('system.access_title'))
@section('page_title', __('system.access_title'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[60rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3">{{ __('system.users_title') }}</th>
                        <th class="py-3">{{ __('system.global_role') }}</th>
                        <th class="py-3">{{ __('system.clinic') }}</th>
                        <th class="py-3">{{ __('system.clinic_role') }}</th>
                        <th class="py-3">{{ __('system.status') }}</th>
                        <th class="py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($users as $dashboardUser)
                        <tr>
                            <form method="POST" action="{{ route('admin.system.access.update', $dashboardUser) }}">
                                @csrf
                                @method('PUT')
                                <td class="py-3 font-bold">{{ $dashboardUser->name }}<p class="text-xs text-zinc-500">{{ $dashboardUser->phone_normalized }}</p></td>
                                <td class="py-3">
                                    <select name="role" class="rounded-lg border border-zinc-200 px-2 py-2">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->code }}" @selected($dashboardUser->role === $role->code)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3">
                                    <select name="tenant_id" class="rounded-lg border border-zinc-200 px-2 py-2">
                                        <option value="">{{ __('messages.none') }}</option>
                                        @foreach($tenants as $tenantOption)
                                            <option value="{{ $tenantOption->id }}">{{ $tenantOption->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3">
                                    <select name="tenant_role" class="rounded-lg border border-zinc-200 px-2 py-2">
                                        <option value="">{{ __('messages.none') }}</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->code }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3">
                                    <select name="status" class="rounded-lg border border-zinc-200 px-2 py-2">
                                        @foreach(['active', 'suspended', 'disabled'] as $status)
                                            <option value="{{ $status }}" @selected($dashboardUser->status === $status)>{{ __('system.statuses.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="tenant_status" value="active">
                                </td>
                                <td class="py-3"><button class="rounded-lg bg-zinc-950 px-3 py-2 text-xs font-bold text-white">{{ __('system.save') }}</button></td>
                            </form>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $users->links() }}</div>
    </section>
@endsection
