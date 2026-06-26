@extends('admin.layout')

@section('title', 'سرویس پیامک جدید')
@section('page_title', 'سرویس پیامک جدید')

@section('content')
<form method="POST" action="{{ route('admin.sms.providers.store') }}" class="admin-panel max-w-3xl space-y-5">
    @csrf
    @include('admin.sms.providers.partials.form', ['provider' => null])
    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.sms.providers.index') }}" class="admin-button admin-button-secondary">انصراف</a>
        <button class="admin-button admin-button-primary">ذخیره</button>
    </div>
</form>
@endsection
