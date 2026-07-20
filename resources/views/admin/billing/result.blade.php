@extends('admin.layout')

@section('title', __('payments.result.page_title'))
@section('page_title', __('payments.result.page_title'))

@php
    $presentation = match ($resultState) {
        'success' => [
            'icon' => 'check',
            'panel' => 'border-emerald-200 bg-emerald-50',
            'icon_box' => 'bg-emerald-600 text-white',
            'status_text' => 'text-emerald-700',
            'dot' => 'bg-emerald-500',
            'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        ],
        'failed' => [
            'icon' => 'x',
            'panel' => 'border-rose-200 bg-rose-50',
            'icon_box' => 'bg-rose-600 text-white',
            'status_text' => 'text-rose-700',
            'dot' => 'bg-rose-500',
            'badge' => 'border-rose-200 bg-rose-50 text-rose-700',
        ],
        default => [
            'icon' => 'credit-card',
            'panel' => 'border-amber-200 bg-amber-50',
            'icon_box' => 'bg-amber-500 text-white',
            'status_text' => 'text-amber-800',
            'dot' => 'bg-amber-500',
            'badge' => 'border-amber-200 bg-amber-50 text-amber-800',
        ],
    };
    $transactionTime = \App\Support\JalaliDate::toJalaliDateTime($payment->verified_at ?? $payment->created_at);
    $maskedAuthority = filled($payment->authority) ? '•••• '.substr((string) $payment->authority, -8) : __('messages.none');
@endphp

@section('content')
    <x-admin.card class="mx-auto max-w-3xl" data-payment-result="{{ $resultState }}">
        <header class="rounded-xl border p-4 sm:p-5 {{ $presentation['panel'] }}" role="status" aria-live="polite" data-payment-result-header="compact">
            <div class="flex items-start gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full shadow-sm {{ $presentation['icon_box'] }}">
                    <x-admin.icon :name="$presentation['icon']" class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold {{ $presentation['status_text'] }}">{{ __('payments.result.'.$resultState.'.badge') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-zinc-950">{{ __('payments.result.'.$resultState.'.title') }}</h2>
                    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-zinc-600">{{ __('payments.result.'.$resultState.'.description') }}</p>
                </div>
            </div>
        </header>

        <div class="mt-6 flex items-center justify-between gap-3">
            <h3 class="text-base font-bold text-zinc-950">{{ __('payments.result.details_title') }}</h3>
            <span class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-bold {{ $presentation['badge'] }}">
                <span class="h-2 w-2 rounded-full {{ $presentation['dot'] }}" aria-hidden="true"></span>
                {{ __('payments.payment_statuses.'.$payment->status) }}
            </span>
        </div>

        <div class="mt-3" data-payment-details="receipt">
            <dl class="grid gap-3 sm:grid-cols-3" data-payment-receipt-summary>
                <div class="rounded-xl bg-zinc-50 p-4">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('payments.fields.amount') }}</dt>
                    <dd class="mt-2 text-lg font-bold text-zinc-950 sm:text-xl" data-payment-amount>
                        {{ number_format($payment->amount) }} <span class="text-xs text-zinc-500">{{ $payment->currency }}</span>
                    </dd>
                </div>
                <div class="rounded-xl bg-zinc-50 p-4">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('payments.fields.number') }}</dt>
                    <dd class="mt-2 text-lg font-bold text-zinc-950 sm:text-xl" dir="ltr">{{ $invoice->number }}</dd>
                </div>
                <div class="rounded-xl bg-zinc-50 p-4">
                    @if(filled($payment->reference_id))
                        <dt class="text-xs font-bold text-zinc-500">{{ __('payments.fields.reference_id') }}</dt>
                        <dd class="mt-2 text-lg font-bold text-emerald-700 sm:text-xl" dir="ltr" data-payment-reference>{{ $payment->reference_id }}</dd>
                    @else
                        <dt class="text-xs font-bold text-zinc-500">{{ __('payments.result.request_code') }}</dt>
                        <dd class="mt-2 text-lg font-bold text-zinc-700 sm:text-xl" dir="ltr" data-masked-authority>{{ $maskedAuthority }}</dd>
                    @endif
                </div>
            </dl>

            <dl class="mt-3 grid gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 sm:grid-cols-2">
                <div class="bg-white p-4">
                    <dt class="text-xs text-zinc-500">{{ __('payments.fields.plan') }}</dt>
                    <dd class="mt-1 text-sm font-bold text-zinc-950">{{ $invoice->plan?->name ?? __('messages.none') }}</dd>
                </div>
                <div class="bg-white p-4">
                    <dt class="text-xs text-zinc-500">{{ __('payments.fields.gateway') }}</dt>
                    <dd class="mt-1 text-sm font-bold text-zinc-950">{{ $payment->gateway?->name ?? __('messages.none') }}</dd>
                </div>
                <div class="bg-white p-4">
                    <dt class="text-xs text-zinc-500">{{ __('payments.result.transaction_time') }}</dt>
                    <dd class="mt-1 text-sm font-bold text-zinc-950" dir="ltr">{{ $transactionTime ?? __('messages.none') }}</dd>
                </div>
                <div class="bg-white p-4">
                    <dt class="text-xs text-zinc-500">{{ __('payments.fields.payment_status') }}</dt>
                    <dd class="mt-1 inline-flex items-center gap-2 text-sm font-bold {{ $presentation['status_text'] }}">
                        <span class="h-2 w-2 rounded-full {{ $presentation['dot'] }}" aria-hidden="true"></span>
                        {{ __('payments.payment_statuses.'.$payment->status) }}
                    </dd>
                </div>
                @if(filled($payment->reference_id))
                    <div class="flex items-center justify-between gap-3 bg-white p-4 sm:col-span-2">
                        <dt class="text-xs text-zinc-500">{{ __('payments.result.request_code') }}</dt>
                        <dd class="text-xs font-bold text-zinc-600" dir="ltr" data-masked-authority>{{ $maskedAuthority }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if($resultState !== 'success')
            <div class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-bold leading-6 text-amber-800" role="note">
                <x-admin.icon name="shield" class="mt-1 h-4 w-4 shrink-0" />
                <p>{{ __('payments.result.deducted_hint') }}</p>
            </div>
        @else
            <p class="mt-4 flex items-center gap-2 text-xs text-zinc-500" role="note">
                <x-admin.icon name="shield" class="h-4 w-4 shrink-0 text-teal-700" />
                <span>{{ __('payments.result.security_note') }}</span>
            </p>
        @endif

        <div class="mt-6 rounded-xl bg-zinc-50 p-3 sm:p-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                @if($resultState === 'failed' && in_array($invoice->status, ['pending', 'failed'], true))
                    <form method="POST" action="{{ route('admin.billing.pay', $invoice) }}" class="sm:w-auto" data-payment-retry-form>
                        @csrf
                        <x-admin.button type="submit" variant="teal" icon="credit-card" block>
                            <span>{{ __('payments.result.retry') }}</span>
                        </x-admin.button>
                    </form>
                    <x-admin.button :href="route('admin.billing.index')" variant="secondary" icon="invoice">
                        <span>{{ __('payments.result.back_to_invoices') }}</span>
                    </x-admin.button>
                @else
                    <x-admin.button :href="route('admin.billing.index')" variant="primary" icon="invoice">
                        <span>{{ __('payments.result.back_to_invoices') }}</span>
                    </x-admin.button>
                    <x-admin.button :href="route('admin.dashboard')" variant="secondary" icon="layout-dashboard">
                        <span>{{ __('payments.result.back_to_dashboard') }}</span>
                    </x-admin.button>
                @endif
            </div>
        </div>
    </x-admin.card>
@endsection
