@extends('admin.layout')

@section('title', __('payments.title'))
@section('page_title', __('payments.title'))

@php
    $payableStatuses = ['pending', 'failed'];
    $invoiceStatusTone = fn (string $status): string => $status === 'paid' ? 'emerald' : ($status === 'failed' ? 'rose' : 'zinc');
    $formatDateTime = fn ($date): string => \App\Support\JalaliDate::toJalaliDateTime($date) ?? __('messages.none');
    $maskAuthority = fn ($authority): string => filled($authority) ? '•••• '.substr((string) $authority, -8) : __('messages.none');
@endphp

@section('content')
    @if($showActiveSubscriptionBox)
        <x-admin.card class="mb-5" data-active-subscription-card>
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-teal-50 text-teal-700">
                    <x-admin.icon name="credit-card" />
                </span>
                <h2 class="text-lg font-bold">{{ __('payments.active_subscription') }}</h2>
            </div>
            @if($activeSubscription)
                <div class="mt-4 grid gap-3 lg:grid-cols-4">
                    <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.plan') }}:</span> <strong>{{ $activeSubscription->plan?->name }}</strong></p>
                    <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.ends_at') }}:</span> <strong><x-jalali-date :value="$activeSubscription->ends_at" /></strong></p>
                    <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.credit') }}:</span> <strong>{{ number_format($remainingCredit) }} / {{ number_format($totalCredit) }}</strong></p>
                    <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.status') }}:</span> <strong>{{ __('payments.subscription_statuses.'.$activeSubscription->status) }}</strong></p>
                </div>
                <div class="mt-4">
                    <h3 class="text-sm font-bold text-zinc-700">{{ __('payments.service_balances') }}</h3>
                    <div class="mt-2 grid gap-2 lg:grid-cols-2 xl:grid-cols-3">
                        @forelse($serviceSummaries as $service)
                            <div class="rounded-lg border border-zinc-100 bg-white p-3">
                                <p class="font-bold text-zinc-950">{{ $service['label'] }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ __('subscriptions.statuses.'.$service['status']) }}</p>
                            </div>
                        @empty
                            <x-admin.empty-state icon="category" class="w-full py-2 lg:col-span-2 xl:col-span-3">{{ __('dashboard.plan.no_service_credit') }}</x-admin.empty-state>
                        @endforelse
                    </div>
                </div>
                @can('permission', 'reports.user.view')
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-admin.button :href="route('admin.reports.credit-usage')" variant="secondary" size="sm" icon="chart">
                            <span>{{ __('reports.credit_usage_title') }}</span>
                        </x-admin.button>
                    </div>
                @endcan
            @else
                <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">{{ __('payments.no_active_subscription') }}</p>
            @endif
        </x-admin.card>
    @endif

    <x-admin.card>
        <x-admin.list-filters
            class="mb-5 mt-0"
            preset-key="admin.billing.index"
            :search-placeholder="__('payments.invoice_search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('payments.fields.status'), 'type' => 'select', 'placeholder' => __('payments.all_statuses'), 'options' => __('payments.invoice_statuses')],
            ]"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'number' => __('payments.fields.number'),
                'amount' => __('payments.fields.amount'),
                'status' => __('payments.fields.status'),
            ]"
        />

        <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-[50rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="number" :label="__('payments.fields.number')" /></th>
                        <th class="px-4 py-3">{{ __('payments.fields.user') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.plan') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="amount" :label="__('payments.fields.amount')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('payments.fields.status')" /></th>
                        <th class="px-4 py-3">{{ __('payments.quick_payment') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($invoices as $invoice)
                        @php
                            $modalId = 'invoice-details-'.$invoice->id;
                            $isPayable = in_array($invoice->status, $payableStatuses, true);
                        @endphp
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    <label
                                        for="{{ $modalId }}-toggle"
                                        class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700"
                                        role="button"
                                        tabindex="0"
                                        data-table-action-item
                                        data-table-row-view-action
                                        data-admin-modal-trigger="{{ $modalId }}"
                                    >
                                        <x-admin.icon name="eye" class="h-4 w-4" />
                                        <span>{{ __('payments.view') }}</span>
                                    </label>
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold" dir="ltr">{{ $invoice->number }}</td>
                            <td class="px-4 py-3">{{ $invoice->user?->name }}</td>
                            <td class="px-4 py-3">{{ $invoice->plan?->name ?? __('messages.none') }}</td>
                            <td class="px-4 py-3">{{ number_format($invoice->amount) }} {{ $invoice->currency }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$invoiceStatusTone($invoice->status)">{{ __('payments.invoice_statuses.'.$invoice->status) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3">
                                @if($isPayable)
                                    <form method="POST" action="{{ route('admin.billing.pay', $invoice) }}" class="inline-flex" data-invoice-direct-pay="{{ $invoice->id }}">
                                        @csrf
                                        <x-admin.button type="submit" variant="teal" size="xs" icon="credit-card">
                                            <span>{{ __('payments.pay') }}</span>
                                        </x-admin.button>
                                    </form>
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('messages.none') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12">
                                <x-admin.empty-state icon="credit-card">{{ __('payments.empty_invoices') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($invoices as $invoice)
                @php
                    $modalId = 'invoice-details-'.$invoice->id;
                    $isPayable = in_array($invoice->status, $payableStatuses, true);
                @endphp
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title" dir="ltr">{{ $invoice->number }}</p>
                            <p class="admin-mobile-list-card-subtitle">{{ $invoice->plan?->name ?? __('messages.none') }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            <label
                                for="{{ $modalId }}-toggle"
                                class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700"
                                role="button"
                                tabindex="0"
                                data-table-action-item
                                data-table-row-view-action
                                data-admin-modal-trigger="{{ $modalId }}"
                            >
                                <x-admin.icon name="eye" class="h-4 w-4" />
                                <span>{{ __('payments.view') }}</span>
                            </label>
                        </x-admin.table.action-menu>
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('payments.fields.amount')">
                            {{ number_format($invoice->amount) }} {{ $invoice->currency }}
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('payments.fields.status')">
                            <x-admin.badge :tone="$invoiceStatusTone($invoice->status)">{{ __('payments.invoice_statuses.'.$invoice->status) }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('payments.fields.user')">
                            <span class="truncate">{{ $invoice->user?->name }}</span>
                        </x-admin.mobile-list.field>
                    </dl>

                    @if($isPayable)
                        <form method="POST" action="{{ route('admin.billing.pay', $invoice) }}" class="mt-3" data-invoice-direct-pay="{{ $invoice->id }}">
                            @csrf
                            <x-admin.button type="submit" variant="teal" size="sm" icon="credit-card" block>
                                <span>{{ __('payments.pay') }}</span>
                            </x-admin.button>
                        </form>
                    @endif
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="credit-card">{{ __('payments.empty_invoices') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        @foreach($invoices as $invoice)
            @php
                $modalId = 'invoice-details-'.$invoice->id;
                $latestPayment = $invoice->payments->sortByDesc('id')->first();
            @endphp
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
                                <dd class="font-bold text-zinc-950">{{ $invoice->tenant?->name ?? __('messages.none') }}</dd>
                            </div>
                            <div class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-2">
                                <dt class="text-zinc-500">{{ __('payments.fields.user') }}</dt>
                                <dd class="font-bold text-zinc-950">{{ $invoice->user?->name ?? __('messages.none') }}</dd>
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
        @endforeach

        <x-admin.table.pagination-footer :paginator="$invoices" />
    </x-admin.card>
@endsection
