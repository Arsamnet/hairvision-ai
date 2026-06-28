@extends('admin.layout')

@section('title', __('leads.create_title'))
@section('page_title', __('leads.create_title'))

@section('content')
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <form method="POST" action="{{ route('admin.leads.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('admin.leads.form', ['formKey' => 'create_lead', 'returnToIndex' => false])
        </form>
    </section>
@endsection
