@extends('admin.layout')

@section('title', __('payments.title'))
@section('page_title', __('payments.title'))

@section('content')
    <section class="mb-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
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
    </section>

    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="overflow-x-auto rounded-lg border border-zinc-100">
            <table class="w-full min-w-[54rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('payments.fields.number') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.user') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.plan') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.amount') }}</th>
                        <th class="px-4 py-3">{{ __('payments.fields.status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($invoices as $invoice)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 font-bold" dir="ltr">{{ $invoice->number }}</td>
                            <td class="px-4 py-3">{{ $invoice->user?->name }}</td>
                            <td class="px-4 py-3">{{ $invoice->plan?->name ?? __('messages.none') }}</td>
                            <td class="px-4 py-3">{{ number_format($invoice->amount) }} {{ $invoice->currency }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-700">{{ __('payments.invoice_statuses.'.$invoice->status) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if(in_array($invoice->status, ['pending', 'failed'], true))
                                    <form method="POST" action="{{ route('admin.billing.pay', $invoice) }}">
                                        @csrf
                                        <button class="inline-flex items-center gap-2 rounded-lg bg-zinc-950 px-3 py-2 text-xs font-bold text-white transition hover:bg-teal-700">
                                            <x-admin.icon name="credit-card" class="h-4 w-4" />
                                            <span>{{ __('payments.pay') }}</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-zinc-500">{{ __('payments.empty_invoices') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $invoices->links() }}</div>
    </section>
@endsection
