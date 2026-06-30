@extends('admin.layout')

@section('title', __('payments.title'))
@section('page_title', __('payments.title'))

@section('content')
    <x-admin.card class="mb-5">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-lg bg-teal-50 text-teal-700">
                <x-admin.icon name="credit-card" />
            </span>
            <h2 class="text-lg font-bold">{{ __('payments.active_subscription') }}</h2>
        </div>
        @if($activeSubscription)
            <div class="mt-4 grid gap-3 md:grid-cols-4">
                <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.plan') }}:</span> <strong>{{ $activeSubscription->plan?->name }}</strong></p>
                <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.ends_at') }}:</span> <strong>{{ $activeSubscription->ends_at?->toDateString() }}</strong></p>
                <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.credit') }}:</span> <strong>{{ number_format(max(0, $activeSubscription->usage_limit - $activeSubscription->usage_used)) }}</strong></p>
                <p class="rounded-lg bg-zinc-50 p-3"><span class="text-zinc-500">{{ __('payments.status') }}:</span> <strong>{{ __('payments.subscription_statuses.'.$activeSubscription->status) }}</strong></p>
            </div>
        @else
            <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">{{ __('payments.no_active_subscription') }}</p>
        @endif
    </x-admin.card>

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

        <div data-admin-table-container class="overflow-visible rounded-lg border border-zinc-100">
            <table class="w-full min-w-[50rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.number') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.user') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.plan') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.amount') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($invoices as $invoice)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @if(in_array($invoice->status, ['pending', 'failed'], true))
                                        <x-admin.table.action-item :action="route('admin.billing.pay', $invoice)" icon="credit-card">{{ __('payments.pay') }}</x-admin.table.action-item>
                                    @endif
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3 font-bold" dir="ltr">{{ $invoice->number }}</td>
                            <td class="px-4 py-3">{{ $invoice->user?->name }}</td>
                            <td class="px-4 py-3">{{ $invoice->plan?->name ?? __('messages.none') }}</td>
                            <td class="px-4 py-3">{{ number_format($invoice->amount) }} {{ $invoice->currency }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$invoice->status === 'paid' ? 'emerald' : ($invoice->status === 'failed' ? 'rose' : 'zinc')">{{ __('payments.invoice_statuses.'.$invoice->status) }}</x-admin.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="credit-card">{{ __('payments.empty_invoices') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $invoices->links() }}</div>
    </x-admin.card>
@endsection
