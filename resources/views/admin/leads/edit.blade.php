@extends('admin.layout')

@section('title', 'ویرایش لید')
@section('page_title', 'ویرایش لید')

@section('content')
    <?php $editorMode = auth()->user()->roleForTenant($lead->tenant_id) === 'editor'; ?>

    <section class="admin-panel">
        <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')

            @unless($editorMode)
                <div>
                    <label class="block text-sm font-bold text-zinc-800">نام کامل</label>
                    <input name="full_name" value="{{ old('full_name', $lead->full_name) }}" class="admin-input mt-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                    <input name="phone_normalized" value="{{ old('phone_normalized', \App\Support\AdminDashboardFormat::phone($lead->phone_normalized)) }}" inputmode="numeric" class="admin-input mt-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800">وضعیت</label>
                    <select name="status" class="admin-select mt-2">
                        @foreach(['new', 'verified', 'contacted', 'qualified', 'archived'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $lead->status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800">مسئول</label>
                    <select name="assigned_user_id" class="admin-select mt-2">
                        <option value="">بدون مسئول</option>
                        @foreach($assignableUsers as $assignableUser)
                            <option value="{{ $assignableUser->id }}" @selected(old('assigned_user_id', $lead->assigned_user_id) == $assignableUser->id)>{{ $assignableUser->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless

            <div>
                <label class="block text-sm font-bold text-zinc-800">منبع</label>
                <input name="source" value="{{ old('source', $lead->source) }}" class="admin-input mt-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">مرحله CRM</label>
                <select name="crm_stage" class="admin-select mt-2">
                    @foreach(['new_lead', 'needs_follow_up', 'consultation_booked', 'converted', 'lost'] as $stage)
                        <option value="{{ $stage }}" @selected(old('crm_stage', $lead->crm_stage) === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-zinc-800">یادداشت جدید</label>
                <textarea name="note" rows="4" class="admin-textarea mt-2">{{ old('note') }}</textarea>
            </div>
            <div class="flex flex-wrap gap-3 md:col-span-2">
                <button class="admin-button admin-button-primary">ذخیره تغییرات</button>
                <a href="{{ route('admin.leads.show', $lead) }}" class="admin-button admin-button-soft">انصراف</a>
            </div>
        </form>
    </section>
@endsection
