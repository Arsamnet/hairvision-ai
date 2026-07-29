@php($latestPayment = $invoice->payments->sortByDesc('id')->first())

<x-admin.modal :id="$modalId" :label="__('payments.invoice_details')" :title="__('payments.invoice_details').' '.$invoice->number" icon="invoice" :trigger="false">
    <div class="grid gap-5 lg:grid-cols-2">
        <section>
            <h3 class="text-sm font-bold text-zinc-800">{{ __('payments.invoice_information') }}</h3>
            <dl class="mt-3 divide-y divide-zinc-100 rounded-lg border border-zinc-100">
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.number') }}</dt>
                    <dd class="font-bold text-zinc-950" dir="ltr">{{ $invoice->number }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.plan') }}</dt>
                    <dd class="font-bold text-zinc-950">{{ $invoice->plan?->name ?? __('messages.none') }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.tenant') }}</dt>
                    <dd class="font-bold text-zinc-950">{{ $clinic->name }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.user') }}</dt>
                    <dd class="font-bold text-zinc-950">{{ $invoice->user?->name ?? __('clinics.clinic_level_invoice') }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.amount') }}</dt>
                    <dd class="font-bold text-zinc-950">{{ number_format($invoice->amount) }} {{ $invoice->currency }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.status') }}</dt>
                    <dd><x-admin.badge :tone="$invoiceStatusTone($invoice->status)">{{ __('payments.invoice_statuses.'.$invoice->status) }}</x-admin.badge></dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.created_at') }}</dt>
                    <dd class="font-bold text-zinc-950" dir="ltr">{{ $formatDateTime($invoice->created_at) }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.due_at') }}</dt>
                    <dd class="font-bold text-zinc-950" dir="ltr">{{ $formatDateTime($invoice->due_at) }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.expires_at') }}</dt>
                    <dd class="font-bold text-zinc-950" dir="ltr">{{ $formatDateTime($invoice->expires_at) }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.paid_at') }}</dt>
                    <dd class="font-bold text-zinc-950" dir="ltr">{{ $formatDateTime($invoice->paid_at) }}</dd>
                </div>
                <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                    <dt class="text-zinc-500">{{ __('payments.fields.activated_at') }}</dt>
                    <dd class="font-bold text-zinc-950" dir="ltr">{{ $formatDateTime($invoice->activated_at) }}</dd>
                </div>
            </dl>
        </section>

        <section>
            <h3 class="text-sm font-bold text-zinc-800">{{ __('payments.payment_information') }}</h3>
            @if($latestPayment)
                <dl class="mt-3 divide-y divide-zinc-100 rounded-lg border border-zinc-100">
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.latest_payment') }}</dt>
                        <dd class="font-bold text-zinc-950" dir="ltr">#{{ $latestPayment->id }}</dd>
                    </div>
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.fields.gateway') }}</dt>
                        <dd class="font-bold text-zinc-950">{{ $latestPayment->gateway?->name ?? __('messages.none') }}</dd>
                    </div>
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.fields.payment_status') }}</dt>
                        <dd><x-admin.badge :tone="$invoiceStatusTone($latestPayment->status)">{{ __('payments.payment_statuses.'.$latestPayment->status) }}</x-admin.badge></dd>
                    </div>
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.fields.amount') }}</dt>
                        <dd class="font-bold text-zinc-950">{{ number_format($latestPayment->amount) }} {{ $latestPayment->currency }}</dd>
                    </div>
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.fields.authority') }}</dt>
                        <dd class="font-bold text-zinc-950" dir="ltr">{{ $maskAuthority($latestPayment->authority) }}</dd>
                    </div>
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.fields.reference_id') }}</dt>
                        <dd class="font-bold text-zinc-950" dir="ltr">{{ $latestPayment->reference_id ?? __('messages.none') }}</dd>
                    </div>
                    <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                        <dt class="text-zinc-500">{{ __('payments.fields.created_at') }}</dt>
                        <dd class="font-bold text-zinc-950" dir="ltr">{{ $formatDateTime($latestPayment->created_at) }}</dd>
                    </div>
                </dl>
            @else
                <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm font-bold text-zinc-600">{{ __('payments.no_payment_attempt') }}</p>
            @endif
        </section>
    </div>
</x-admin.modal>
