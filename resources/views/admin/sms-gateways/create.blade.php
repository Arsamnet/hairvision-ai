@extends('admin.layout')

@section('title', __('system.new_private_gateway'))
@section('page_title', __('system.new_private_gateway'))

@section('content')
    <form method="POST" action="{{ route('admin.sms-gateways.store') }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        @csrf
        @include('admin.sms-gateways.form', ['gateway' => null])
    </form>
@endsection
