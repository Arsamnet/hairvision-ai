@extends('admin.layout')

@section('title', __('system.role_edit_title'))
@section('page_title', __('system.role_edit_title'))

@section('content')
    <form method="POST" action="{{ route('admin.system.roles.update', $role) }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        @csrf
        @method('PUT')
        <div class="grid gap-4 md:grid-cols-3">
            <label class="block">
                <span class="text-sm font-bold text-zinc-600">{{ __('system.name') }}</span>
                <input name="name" value="{{ old('name', $role->name) }}" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="text-sm font-bold text-zinc-600">{{ __('system.status') }}</span>
                <select name="status" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    @foreach(['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected($role->status === $status)>{{ __('system.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block md:col-span-3">
                <span class="text-sm font-bold text-zinc-600">{{ __('system.description') }}</span>
                <input name="description" value="{{ old('description', $role->description) }}" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm">
            </label>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($permissions as $group => $groupPermissions)
                <fieldset class="rounded-lg border border-zinc-200 p-4">
                    <legend class="px-2 text-sm font-bold text-zinc-700">{{ $group }}</legend>
                    <div class="mt-3 space-y-2">
                        @foreach($groupPermissions as $permission)
                            <label class="flex items-center gap-2 text-sm text-zinc-700">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id))>
                                <span dir="ltr">{{ $permission->code }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        <x-admin.button type="submit" size="compact-md" class="mt-5">{{ __('system.save') }}</x-admin.button>
    </form>
@endsection
