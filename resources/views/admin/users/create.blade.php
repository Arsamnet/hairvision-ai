@extends('admin.layout')

@section('title', __('system.users.create'))
@section('page_title', __('system.users.create'))

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        @csrf
        @include('admin.users.form', ['dashboardUser' => null])
    </form>
@endsection
