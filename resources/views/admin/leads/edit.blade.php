@extends('admin.layout')

@section('title', __('leads.edit_title'))
@section('page_title', __('leads.edit_title'))

@section('content')
    <?php $editorMode = auth()->user()->roleForTenant($lead->tenant_id) === 'editor'; ?>

    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')

            @unless($editorMode)
                <div>
                    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.full_name') }}</label>
                    <input name="full_name" value="{{ old('full_name', $lead->full_name) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.mobile') }}</label>
                    <input name="phone_normalized" value="{{ old('phone_normalized', $lead->phone_normalized) }}" inputmode="numeric" data-digit-input class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500" dir="ltr">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.status') }}</label>
                    <select name="status" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
                        @foreach(\App\Support\StateCatalog::options('leads.statuses') as $status => $label)
                            <option value="{{ $status }}" @selected(old('status', $lead->status) === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.owner') }}</label>
                    <select name="assigned_user_id" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
                        <option value="">{{ __('leads.no_owner') }}</option>
                        @foreach($assignableUsers as $assignableUser)
                            <option value="{{ $assignableUser->id }}" @selected(old('assigned_user_id', $lead->assigned_user_id) == $assignableUser->id)>{{ $assignableUser->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless

            <div>
                <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.source') }}</label>
                <input name="source" value="{{ old('source', $lead->source) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800">{{ __('leads.fields.crm_stage') }}</label>
                <select name="crm_stage" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 outline-none focus:border-teal-500">
                    @foreach(\App\Support\StateCatalog::options('leads.crm_stages') as $stage => $label)
                        <option value="{{ $stage }}" @selected(old('crm_stage', $lead->crm_stage) === $stage)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-zinc-800">{{ __('leads.new_note') }}</label>
                <textarea name="note" rows="4" class="mt-2 w-full rounded-lg border border-zinc-200 px-3 py-2 outline-none focus:border-teal-500">{{ old('note') }}</textarea>
            </div>
            <div class="flex flex-wrap gap-3 md:col-span-2">
                <x-admin.button type="submit" size="compact-md">{{ __('leads.save_changes') }}</x-admin.button>
                <x-admin.button :href="route('admin.leads.show', $lead)" variant="subtle" size="compact-md">{{ __('leads.cancel') }}</x-admin.button>
            </div>
        </form>
    </section>
@endsection
