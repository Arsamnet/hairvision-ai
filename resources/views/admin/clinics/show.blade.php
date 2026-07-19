@extends('admin.layout')

@section('title', __('clinics.details'))
@section('page_title', __('clinics.details'))

@php
    $activeSubscription = $clinic->subscriptions->firstWhere('status', 'active') ?? $clinic->subscriptions->first();
    $entitlementService = app(\App\Services\Billing\ServiceEntitlementService::class);
    $remainingCredit = $entitlementService->remainingCreditForSubscription($activeSubscription);
    $totalCredit = $entitlementService->totalCreditForSubscription($activeSubscription);
    $assignedAccessIds = $assignedAccesses->pluck('id')->map(fn ($id): int => (int) $id)->all();
    $hasAddableAccess = $availableAccesses->contains(fn ($access): bool => ! in_array((int) $access->id, $assignedAccessIds, true));
    $subscriptionTone = match ($activeSubscription?->status) {
        'active' => 'emerald',
        'expired' => 'amber',
        default => 'zinc',
    };
    $invoiceStatusTone = fn (string $status): string => $status === 'paid' ? 'emerald' : ($status === 'failed' ? 'rose' : ($status === 'canceled' ? 'zinc' : 'amber'));
@endphp

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $clinic->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-500" dir="ltr">{{ $primaryDomain?->domain ?? $clinic->slug }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-admin.button :href="route('admin.clinics.index')" variant="secondary" size="sm" icon="building">
                        <span>{{ __('clinics.title') }}</span>
                    </x-admin.button>
                    @can('update', $clinic)
                        <x-admin.button :href="route('admin.clinics.edit', $clinic)" variant="secondary" size="sm" icon="edit">
                            <span>{{ __('clinics.edit') }}</span>
                        </x-admin.button>
                    @endcan
                </div>
            </div>

            <dl class="mt-5 grid gap-3 lg:grid-cols-6">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-sm text-zinc-500">{{ __('clinics.fields.status') }}</dt>
                    <dd class="mt-1"><x-admin.badge :tone="$clinic->status === 'active' ? 'emerald' : 'zinc'">{{ __('clinics.statuses.'.$clinic->status) }}</x-admin.badge></dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3 lg:col-span-2">
                    <dt class="text-sm text-zinc-500">{{ __('clinics.profile_domain') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ $primaryDomain?->domain ?? __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-sm text-zinc-500">{{ __('clinics.leads_count') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($clinic->leads_count) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-sm text-zinc-500">{{ __('clinics.users_count') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($clinic->users_count) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-sm text-zinc-500">{{ __('clinics.models_count') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($clinic->ai_model_accesses_count) }}</dd>
                </div>
            </dl>
        </x-admin.card>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
            <x-admin.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold">{{ __('clinics.billing_details') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('clinics.plan_invoice_hint') }}</p>
                    </div>
                    @if(auth()->user()?->hasPermission('billing.manage') && $plans->isNotEmpty())
                        <x-admin.modal id="assign-plan-modal" :label="__('clinics.assign_plan')" :title="__('clinics.assign_plan')" icon="package" :open="$errors->any() && old('_form') === 'assign_clinic_plan_'.$clinic->id">
                            <form method="POST" action="{{ route('admin.clinics.plans.assign', $clinic) }}" class="grid gap-3">
                                @csrf
                                <input type="hidden" name="_form" value="assign_clinic_plan_{{ $clinic->id }}">
                                <x-admin.validation-summary for="assign_clinic_plan_{{ $clinic->id }}" />
	                                <select name="plan_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
	                                    @foreach($plans as $plan)
	                                        <option value="{{ $plan->id }}" @selected((int) old('plan_id') === $plan->id)>
	                                            {{ $plan->name }} - {{ number_format($plan->price) }} {{ $plan->currency }}
	                                        </option>
	                                    @endforeach
	                                </select>
                                    <label class="text-xs font-bold text-zinc-600">
                                        <span>{{ __('clinics.discount_amount') }}</span>
                                        <input name="discount_amount" type="number" min="0" value="{{ old('discount_amount', 0) }}" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
                                    </label>
	                                <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm font-bold leading-6 text-amber-800">{{ __('clinics.assign_plan_invoice_hint') }}</p>
                                <x-admin.button type="submit" icon="check">
                                    <span>{{ __('clinics.issue_invoice') }}</span>
                                </x-admin.button>
                            </form>
                        </x-admin.modal>
                    @endif
                </div>

                @if($activeSubscription)
                    <dl class="mt-4 grid gap-3 md:grid-cols-4">
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('clinics.plan') }}</dt>
                            <dd class="mt-1 font-bold">{{ $activeSubscription->plan?->name ?? __('messages.none') }}</dd>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('payments.status') }}</dt>
                            <dd class="mt-1"><x-admin.badge :tone="$subscriptionTone">{{ __('payments.subscription_statuses.'.$activeSubscription->status) }}</x-admin.badge></dd>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('subscriptions.remaining_credit') }}</dt>
                            <dd class="mt-1 font-bold" dir="ltr">{{ number_format($remainingCredit) }} / {{ number_format($totalCredit) }}</dd>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('subscriptions.expires_at') }}</dt>
                            <dd class="mt-1 font-bold"><x-jalali-date :value="$activeSubscription->ends_at" /></dd>
                        </div>
                    </dl>

                    <div class="mt-4 grid gap-2 md:grid-cols-2">
                        @foreach($activeSubscription->serviceEntitlements as $entitlement)
                            <div class="rounded-lg bg-zinc-50 p-3 text-sm">
                                <p class="font-bold">{{ $serviceOptions[$entitlement->service_key] ?? $entitlement->service_key }}</p>
                                <p class="mt-2 text-xs text-zinc-500">{{ __('subscriptions.available') }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4">
                        <x-admin.empty-state icon="package">{{ __('clinics.no_active_plan') }}</x-admin.empty-state>
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold">{{ __('clinics.sms_gateway') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('clinics.sms_gateway_hint') }}</p>
                    </div>
                    @if(auth()->user()?->hasAnyPermission(['sms_gateways.manage', 'sms_gateways.edit']))
                        <x-admin.modal id="clinic-sms-gateway-modal" :label="__('clinics.edit_sms_gateway')" :title="__('clinics.edit_sms_gateway')" icon="message" :open="$errors->any() && old('_form') === 'clinic_sms_gateway'">
                            <form method="POST" action="{{ route('admin.clinics.sms-gateway.update', $clinic) }}">
                                @csrf
                                @include('admin.sms-gateways.form', [
                                    'gateway' => $clinicSmsGateway,
                                    'formKey' => 'clinic_sms_gateway',
                                    'providerOptions' => $smsProviderOptions,
                                ])
                            </form>
                        </x-admin.modal>
                    @endif
                </div>

                @if($clinicSmsGateway)
                    <dl class="mt-4 grid gap-3">
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('system.provider') }}</dt>
                            <dd class="mt-1 font-bold" dir="ltr">{{ $clinicSmsGateway->provider_code }}</dd>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('sms.api_key') }}</dt>
                            <dd class="mt-1 font-bold" dir="ltr">{{ $clinicSmsGateway->maskedCredential('api_key') ?? __('messages.none') }}</dd>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3">
                            <dt class="text-sm text-zinc-500">{{ __('sms.from_number') }}</dt>
                            <dd class="mt-1 font-bold" dir="ltr">{{ data_get($clinicSmsGateway->settings_json, 'from_number', data_get($clinicSmsGateway->settings_json, 'sender')) ?? __('messages.none') }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="mt-4">
                        <x-admin.empty-state icon="message">{{ __('clinics.no_sms_gateway') }}</x-admin.empty-state>
                    </div>
                @endif
            </x-admin.card>
        </div>

        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold">{{ __('clinics.manage_models') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ai_models.assignment_title') }}</p>
                </div>
                @can('assignToClinic', App\Models\AiModelAccess::class)
                    @if($availableAccesses->isNotEmpty())
                        <x-admin.modal
                            id="assign-clinic-ai-model-modal"
                            :label="__('ai_models.assign')"
                            :title="__('ai_models.assign')"
                            icon="plus"
                            :open="$errors->any() && old('_form') === 'assign_clinic_ai_model_'.$clinic->id"
                        >
                            <form method="POST" action="{{ route('admin.clinics.ai-models.store', $clinic) }}" class="grid gap-3">
                                @csrf
                                <input type="hidden" name="_form" value="assign_clinic_ai_model_{{ $clinic->id }}">
                                <x-admin.validation-summary for="assign_clinic_ai_model_{{ $clinic->id }}" />
                                <label class="grid gap-1 text-sm font-bold">
                                    <span>{{ __('ai_models.available_accesses') }}</span>
                                    <select name="ai_model_access_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 disabled:bg-zinc-100 disabled:text-zinc-400" @disabled(! $hasAddableAccess)>
                                        <option value="" disabled selected>{{ __('ai_models.select_access') }}</option>
                                        @foreach($availableAccesses as $access)
                                            @php($isAssigned = in_array((int) $access->id, $assignedAccessIds, true))
                                            <option value="{{ $access->id }}" @disabled($isAssigned)>
                                                {{ $access->name }} - {{ $resolver->usageCategoryLabel($access->usage_category_key) }} @if($isAssigned)({{ __('ai_models.already_assigned') }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
                                    <input type="checkbox" name="is_active" value="1" checked @disabled(! $hasAddableAccess)>
                                    <span>{{ __('ai_models.statuses.active') }}</span>
                                </label>
                                <x-admin.button type="submit" icon="plus" :disabled="! $hasAddableAccess" class="{{ ! $hasAddableAccess ? 'opacity-50 pointer-events-none' : '' }}">
                                    <span>{{ __('ai_models.assign') }}</span>
                                </x-admin.button>
                            </form>
                        </x-admin.modal>
                    @endif
                @endcan
            </div>

            <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-216 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3">{{ __('system.name') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.model') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.usage_category') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($assignedAccesses as $access)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        <x-admin.table.action-item :href="route('admin.ai-models.show', $access)" icon="eye">{{ __('clinics.view') }}</x-admin.table.action-item>
                                        @can('assignToClinic', App\Models\AiModelAccess::class)
                                            <x-admin.table.action-item :action="route('admin.clinics.ai-models.update', [$clinic, $access])" method="PATCH" icon="check" :inputs="['ai_model_access_id' => $access->id, 'is_active' => $access->pivot->is_active ? '0' : '1']">{{ __('ai_models.toggle_assignment') }}</x-admin.table.action-item>
                                            <x-admin.table.action-item :action="route('admin.clinics.ai-models.destroy', [$clinic, $access])" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('ai_models.unassign') }}</x-admin.table.action-item>
                                        @endcan
                                    </x-admin.table.action-menu>
                                </td>
                                <td class="px-4 py-3 font-bold">{{ $access->name }}</td>
                                <td class="px-4 py-3">{{ $resolver->modelLabel($access->model_key) }}</td>
                                <td class="px-4 py-3">{{ $resolver->usageCategoryLabel($access->usage_category_key) }}</td>
                                <td class="px-4 py-3"><x-admin.badge :tone="$access->pivot->is_active ? 'emerald' : 'zinc'">{{ $access->pivot->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12"><x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.assignments') }}</x-admin.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($assignedAccesses as $access)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $access->name }}</p>
                                <p class="admin-mobile-list-card-subtitle">{{ $resolver->modelLabel($access->model_key) }}</p>
                            </div>
                            <x-admin.badge :tone="$access->pivot->is_active ? 'emerald' : 'zinc'">{{ $access->pivot->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                        </div>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.assignments') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>
        </x-admin.card>

        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold">{{ __('payments.title') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('payments.manual_invoice_hint') }}</p>
                </div>
                @if(auth()->user()?->hasPermission('billing.manage'))
                    <x-admin.modal id="create-clinic-invoice-modal" :label="__('payments.create_manual_invoice')" :title="__('payments.create_manual_invoice')" icon="credit-card" :open="$errors->any() && old('_form') === 'create_clinic_invoice'">
                        @include('admin.clinics.partials.invoice-form', [
                            'action' => route('admin.clinics.invoices.store', $clinic),
                            'method' => 'POST',
                            'invoice' => null,
                            'plans' => $plans,
                            'users' => $clinic->users,
                            'formKey' => 'create_clinic_invoice',
                        ])
                    </x-admin.modal>
                @endif
            </div>

            <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-[58rem] text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3">{{ __('payments.fields.number') }}</th>
                            <th class="px-4 py-3">{{ __('payments.fields.plan') }}</th>
                            <th class="px-4 py-3">{{ __('payments.fields.user') }}</th>
                            <th class="px-4 py-3">{{ __('payments.fields.amount') }}</th>
                            <th class="px-4 py-3">{{ __('payments.fields.status') }}</th>
                            <th class="px-4 py-3">{{ __('payments.fields.activated_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($clinic->invoices as $invoice)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        @if(auth()->user()?->hasPermission('billing.manage'))
                                            <label for="edit-invoice-{{ $invoice->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="edit-invoice-{{ $invoice->id }}">
                                                <x-admin.icon name="edit" class="h-4 w-4" />
                                                <span>{{ __('system.edit') }}</span>
                                            </label>
                                            @if($invoice->status !== 'paid' || ($invoice->plan_id && ! $invoice->activated_at))
                                                <x-admin.table.action-item :action="route('admin.clinics.invoices.approve', [$clinic, $invoice])" method="PATCH" icon="check">{{ __('payments.approve_invoice') }}</x-admin.table.action-item>
                                            @endif
                                        @endif
                                    </x-admin.table.action-menu>
                                </td>
                                <td class="px-4 py-3 font-bold" dir="ltr">{{ $invoice->number }}</td>
                                <td class="px-4 py-3">{{ $invoice->plan?->name ?? __('messages.none') }}</td>
                                <td class="px-4 py-3">{{ $invoice->user?->name ?? __('clinics.clinic_level_invoice') }}</td>
                                <td class="px-4 py-3">{{ number_format($invoice->amount) }} {{ $invoice->currency }}</td>
                                <td class="px-4 py-3"><x-admin.badge :tone="$invoiceStatusTone($invoice->status)">{{ __('payments.invoice_statuses.'.$invoice->status) }}</x-admin.badge></td>
                                <td class="px-4 py-3"><x-jalali-date-time :value="$invoice->activated_at" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12"><x-admin.empty-state icon="credit-card">{{ __('payments.empty_invoices') }}</x-admin.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($clinic->invoices as $invoice)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title" dir="ltr">{{ $invoice->number }}</p>
                                <p class="admin-mobile-list-card-subtitle">{{ $invoice->plan?->name ?? __('messages.none') }}</p>
                            </div>
                            <x-admin.badge :tone="$invoiceStatusTone($invoice->status)">{{ __('payments.invoice_statuses.'.$invoice->status) }}</x-admin.badge>
                        </div>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="credit-card">{{ __('payments.empty_invoices') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>

            @foreach($clinic->invoices as $invoice)
                <x-admin.modal id="edit-invoice-{{ $invoice->id }}" :label="__('system.edit')" :title="$invoice->number" icon="edit" :trigger="false" :open="$errors->any() && old('_form') === 'edit_clinic_invoice_'.$invoice->id">
                    @include('admin.clinics.partials.invoice-form', [
                        'action' => route('admin.clinics.invoices.update', [$clinic, $invoice]),
                        'method' => 'PUT',
                        'invoice' => $invoice,
                        'plans' => $plans,
                        'users' => $clinic->users,
                        'formKey' => 'edit_clinic_invoice_'.$invoice->id,
                    ])
                </x-admin.modal>
            @endforeach
        </x-admin.card>
    </div>
@endsection
