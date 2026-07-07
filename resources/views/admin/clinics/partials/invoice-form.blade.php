@php
    $invoice = $invoice ?? null;
    $method = $method ?? 'POST';
@endphp

<form method="POST" action="{{ $action }}" class="grid gap-3 md:grid-cols-2">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="_form" value="{{ $formKey }}">
    <x-admin.validation-summary :for="$formKey" class="md:col-span-2" />

    <label class="text-xs font-bold text-zinc-600">
        <span>{{ __('payments.fields.plan') }}</span>
        <select name="plan_id" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            <option value="">{{ __('messages.none') }}</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected((int) old('plan_id', $invoice?->plan_id) === $plan->id)>
                    {{ $plan->name }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="text-xs font-bold text-zinc-600">
        <span>{{ __('payments.fields.user') }}</span>
        <select name="user_id" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            <option value="">{{ __('clinics.clinic_level_invoice') }}</option>
            @foreach($users as $clinicUser)
                <option value="{{ $clinicUser->id }}" @selected((int) old('user_id', $invoice?->user_id) === $clinicUser->id)>
                    {{ $clinicUser->name }} - {{ __('system.roles.'.$clinicUser->pivot->role) }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="text-xs font-bold text-zinc-600">
        <span>{{ __('payments.fields.amount') }}</span>
        <input name="amount" type="number" min="0" value="{{ old('amount', $invoice?->amount) }}" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>

    <label class="text-xs font-bold text-zinc-600">
        <span>{{ __('payments.fields.status') }}</span>
        <select name="status" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach(__('payments.invoice_statuses') as $status => $label)
                <option value="{{ $status }}" @selected(old('status', $invoice?->status ?? 'pending') === $status)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="text-xs font-bold text-zinc-600">
        <span>{{ __('payments.fields.due_at') }}</span>
        <input name="due_at" value="{{ old('due_at', $invoice?->due_at) }}" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="1405/01/31 23:59:59">
    </label>

    <label class="text-xs font-bold text-zinc-600">
        <span>{{ __('payments.fields.expires_at') }}</span>
        <input name="expires_at" value="{{ old('expires_at', $invoice?->expires_at) }}" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="1405/01/31 23:59:59">
    </label>

    <input name="currency" value="{{ old('currency', $invoice?->currency ?? 'IRR') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="{{ __('payments.fields.currency') }}">
    <input name="note" value="{{ old('note', data_get($invoice?->metadata_json, 'manual_note')) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.description') }}">

    <x-admin.button type="submit" icon="check" class="md:col-span-2">
        <span>{{ __('system.save') }}</span>
    </x-admin.button>
</form>
