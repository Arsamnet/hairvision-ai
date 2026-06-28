@extends('admin.layout')

@section('title', __('sms.edit_gateway'))
@section('page_title', __('sms.edit_gateway'))

@section('content')
    <form method="POST" action="{{ route('admin.system.sms-gateways.update', $gateway) }}" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        @csrf
        @method('PUT')
        @include('admin.system.sms-gateways.form', ['gateway' => $gateway])
    </form>
@endsection
