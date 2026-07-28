@extends('admin.layout')

@section('title', __('system.permissions_title'))
@section('page_title', __('system.permissions_title'))

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-bold">{{ __('system.permissions_title') }}</h2>
        <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('permissions.subtitle') }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        @forelse($permissions as $group => $groupPermissions)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <h3 class="font-bold">{{ $permissionGroupLabels[$group] ?? $group }}</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @foreach($groupPermissions as $permission)
                        <p>
                            <span class="font-bold">{{ $permission->display_name }}</span>
                            <span class="mt-0.5 block text-xs text-zinc-500" dir="ltr">{{ $permission->code }}</span>
                        </p>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="rounded-lg bg-white px-5 py-12 shadow-sm ring-1 ring-zinc-200 lg:col-span-2 xl:col-span-3">
                <x-admin.empty-state icon="permission">{{ __('system.empty_permissions') }}</x-admin.empty-state>
            </section>
        @endforelse
    </div>
@endsection
