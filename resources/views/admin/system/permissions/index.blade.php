@extends('admin.layout')

@section('title', __('system.permissions_title'))
@section('page_title', __('system.permissions_title'))

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($permissions as $group => $groupPermissions)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <h2 class="font-bold">{{ $group }}</h2>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @foreach($groupPermissions as $permission)
                        <p><span dir="ltr" class="font-bold">{{ $permission->code }}</span> - {{ $permission->name }}</p>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
