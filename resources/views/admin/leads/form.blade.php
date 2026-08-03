{{-- @since 2.1.0 Uses unified lead status and optional age/city fields. --}}
@php
    $formKey = $formKey ?? 'create_lead';
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
@if($returnToIndex ?? false)
    <input type="hidden" name="return_to_index" value="1">
@endif

<x-admin.validation-summary :for="$formKey" class="md:col-span-2" />

<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.full_name') }}</label>
    <input name="full_name" value="{{ old('full_name') }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
</div>
<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.mobile') }}</label>
    <input name="phone_normalized" value="{{ old('phone_normalized') }}" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" data-digit-input class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" dir="ltr">
</div>
<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.age') }}</label>
    <input name="age" value="{{ old('age') }}" inputmode="numeric" pattern="[0-9۰-۹٠-٩]*" maxlength="3" data-digit-input class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" dir="ltr">
</div>
<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.city') }}</label>
    <input name="city" value="{{ old('city') }}" maxlength="120" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
</div>
<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.source') }}</label>
    <input name="source" value="{{ old('source', 'داشبورد') }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
</div>
<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.owner') }}</label>
    <select name="assigned_user_id" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
        <option value="">{{ __('leads.no_owner') }}</option>
        @foreach($assignableUsers as $assignableUser)
            <option value="{{ $assignableUser->id }}" @selected(old('assigned_user_id') == $assignableUser->id)>{{ $assignableUser->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.status') }}</label>
    <select name="status" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
        @foreach(\App\Support\StateCatalog::options('leads.statuses') as $status => $label)
            <option value="{{ $status }}" @selected(old('status', 'new') === $status)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="md:col-span-2">
    <label class="block text-sm font-bold text-zinc-800">{{ __('crm.note') }}</label>
    <textarea name="note" rows="4" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 outline-none focus:border-teal-500">{{ old('note') }}</textarea>
</div>
<div class="flex flex-wrap gap-3 md:col-span-2">
    <x-admin.button type="submit" size="compact-md">{{ __('leads.submit_create') }}</x-admin.button>
    <x-admin.button :href="route('admin.leads.index')" variant="subtle" size="compact-md">{{ __('leads.cancel') }}</x-admin.button>
</div>
