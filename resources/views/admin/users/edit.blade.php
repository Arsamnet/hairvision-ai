@extends('admin.layout')

@section('title', __('system.users.edit'))
@section('page_title', __('system.users.edit'))

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $dashboardUser) }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        @csrf
        @method('PUT')
        @include('admin.users.form', ['dashboardUser' => $dashboardUser])
    </form>
@endsection
