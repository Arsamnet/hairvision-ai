@extends('admin.layout')

@section('title', __('system.roles_title'))
@section('page_title', __('system.roles_title'))

@section('content')
    @can('permission', 'roles.create')
        <section class="mb-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('system.new_role') }}</h2>
            <form method="POST" action="{{ route('admin.system.roles.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <input name="name" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="{{ __('system.name') }}">
                <input name="code" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm ltr:text-left" dir="ltr" placeholder="code">
                <input name="description" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm md:col-span-2" placeholder="{{ __('system.description') }}">
                <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white">{{ __('system.submit') }}</button>
            </form>
        </section>
    @endcan

    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3">{{ __('system.name') }}</th>
                        <th class="py-3">{{ __('system.code') }}</th>
                        <th class="py-3">{{ __('system.permissions') }}</th>
                        <th class="py-3">{{ __('system.status') }}</th>
                        <th class="py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($roles as $role)
                        <tr>
                            <td class="py-3 font-bold">{{ $role->name }}</td>
                            <td class="py-3 text-zinc-600" dir="ltr">{{ $role->code }}</td>
                            <td class="py-3 text-zinc-600">{{ number_format($role->permissions_count) }}</td>
                            <td class="py-3 text-zinc-600">{{ __('system.statuses.'.$role->status) }}</td>
                            <td class="py-3">
                                @can('permission', 'roles.view')
                                    <a class="font-bold text-teal-700" href="{{ route('admin.system.roles.edit', $role) }}">{{ __('system.edit') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $roles->links() }}</div>
    </section>
@endsection
