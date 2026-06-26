@extends('admin.layout')

@section('title', 'لید جدید')
@section('page_title', 'لید جدید')

@section('content')
    <section class="admin-panel">
        <form method="POST" action="{{ route('admin.leads.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label class="block text-sm font-bold text-zinc-800">نام کامل</label>
                <input name="full_name" value="{{ old('full_name') }}" class="admin-input mt-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                <input name="phone_normalized" value="{{ old('phone_normalized') }}" inputmode="numeric" class="admin-input mt-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">منبع</label>
                <input name="source" value="{{ old('source', 'dashboard') }}" class="admin-input mt-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">مسئول</label>
                <select name="assigned_user_id" class="admin-select mt-2">
                    <option value="">بدون مسئول</option>
                    @foreach($assignableUsers as $assignableUser)
                        <option value="{{ $assignableUser->id }}" @selected(old('assigned_user_id') == $assignableUser->id)>{{ $assignableUser->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">وضعیت</label>
                <select name="status" class="admin-select mt-2">
                    @foreach(['new', 'verified', 'contacted', 'qualified', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', 'verified') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">مرحله CRM</label>
                <select name="crm_stage" class="admin-select mt-2">
                    @foreach(['new_lead', 'needs_follow_up', 'consultation_booked', 'converted', 'lost'] as $stage)
                        <option value="{{ $stage }}" @selected(old('crm_stage', 'new_lead') === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-zinc-800">یادداشت</label>
                <textarea name="note" rows="4" class="admin-textarea mt-2">{{ old('note') }}</textarea>
            </div>
            <div class="flex flex-wrap gap-3 md:col-span-2">
                <button class="admin-button admin-button-primary">ثبت لید</button>
                <a href="{{ route('admin.leads.index') }}" class="admin-button admin-button-soft">انصراف</a>
            </div>
        </form>
    </section>
@endsection
