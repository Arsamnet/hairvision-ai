@extends('admin.layout')

@section('title', __('reports.credit_usage_title'))
@section('page_title', __('reports.credit_usage_title'))

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-lg bg-teal-50 text-teal-700">
                        <x-admin.icon name="credit-card" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold">{{ __('reports.credit_usage_title') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-zinc-500">{{ $activeSubscription?->plan?->name ?? __('payments.no_active_subscription') }}</p>
                    </div>
                </div>
                <x-admin.button :href="route('admin.billing.index')" variant="secondary" size="sm" icon="invoice">
                    <span>{{ __('payments.title') }}</span>
                </x-admin.button>
            </div>

            @if($activeSubscription)
                <dl class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg bg-zinc-50 p-3">
                        <dt class="text-xs font-bold text-zinc-500">{{ __('credits.remaining') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-zinc-950" dir="ltr">{{ number_format($remainingCredit) }}</dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3">
                        <dt class="text-xs font-bold text-zinc-500">{{ __('credits.total') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-zinc-950" dir="ltr">{{ number_format($totalCredit) }}</dd>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3">
                        <dt class="text-xs font-bold text-zinc-500">{{ __('payments.ends_at') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-zinc-950"><x-jalali-date :value="$activeSubscription->ends_at" /></dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse($serviceSummaries as $service)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-teal-50 px-2.5 py-1.5 text-xs font-bold text-teal-700">
                            <x-admin.icon name="category" class="h-3.5 w-3.5" />
                            <span>{{ $service['label'] }}</span>
                        </span>
                    @empty
                        <x-admin.empty-state icon="category" class="w-full py-2">{{ __('dashboard.plan.no_service_credit') }}</x-admin.empty-state>
                    @endforelse
                </div>
            @else
                <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">{{ __('payments.no_active_subscription') }}</p>
            @endif
        </x-admin.card>

        <x-admin.card>
            <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-184 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('reports.fields.date') }}</th>
                            <th class="px-4 py-3">{{ __('reports.fields.description') }}</th>
                            <th class="px-4 py-3">{{ __('reports.fields.type') }}</th>
                            <th class="px-4 py-3">{{ __('reports.fields.amount') }}</th>
                            <th class="px-4 py-3">{{ __('reports.fields.balance_after') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($creditTimeline as $movement)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 font-bold text-zinc-600"><x-jalali-date :value="$movement['created_at']" /></td>
                                <td class="px-4 py-3">{{ $movement['description'] ?: __('credits.transaction_types.'.$movement['type']) }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :tone="$movement['amount'] >= 0 ? 'emerald' : 'rose'">{{ __('credits.transaction_types.'.$movement['type']) }}</x-admin.badge>
                                </td>
                                <td class="px-4 py-3 font-bold {{ $movement['amount'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}" dir="ltr">{{ number_format($movement['amount']) }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ $movement['balance_after'] === null ? __('messages.none') : number_format($movement['balance_after']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12">
                                    <x-admin.empty-state icon="credit-card">{{ __('reports.credit_usage_empty') }}</x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($creditTimeline as $movement)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div>
                                <p class="admin-mobile-list-card-title">{{ $movement['description'] ?: __('credits.transaction_types.'.$movement['type']) }}</p>
                                <p class="admin-mobile-list-card-subtitle"><x-jalali-date :value="$movement['created_at']" /></p>
                            </div>
                            <span class="font-bold {{ $movement['amount'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}" dir="ltr">{{ number_format($movement['amount']) }}</span>
                        </div>
                        <dl class="admin-mobile-list-fields">
                            <x-admin.mobile-list.field :label="__('reports.fields.type')">{{ __('credits.transaction_types.'.$movement['type']) }}</x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('reports.fields.balance_after')">{{ $movement['balance_after'] === null ? __('messages.none') : number_format($movement['balance_after']) }}</x-admin.mobile-list.field>
                        </dl>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="credit-card">{{ __('reports.credit_usage_empty') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>
        </x-admin.card>

        @if($usageLogs)
            @include('admin.ai-models.partials.usage-logs', [
                'showClinic' => false,
                'showModel' => true,
            ])
        @endif
    </div>
@endsection
