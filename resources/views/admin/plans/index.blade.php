@extends('admin.layout')

@section('title', __('plans.title'))
@section('page_title', __('plans.title'))

@section('content')
    @if($canManagePlans)
        <x-admin.card class="mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('plans.management_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('plans.management_hint') }}</p>
                </div>
                <x-admin.modal id="create-plan-modal" :label="__('plans.new_plan')" :title="__('plans.new_plan')" icon="package" :open="$errors->any() && old('_form') === 'create_plan'">
                    <form method="POST" action="{{ route('admin.system.plans.store') }}" class="grid gap-3 lg:grid-cols-4">
                        @csrf
                        <input type="hidden" name="_form" value="create_plan">
                        <x-admin.validation-summary for="create_plan" class="lg:col-span-4" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.name') }}">
                        <select name="plan_type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(__('plans.types') as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="price" type="number" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.price') }}">
                        <input name="duration_days" type="number" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.duration_days') }}">
                        <input name="currency" value="ریال" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.currency') }}">
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(__('plans.statuses') as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="inline-flex h-11 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-bold text-zinc-700">
                            <input name="is_public" type="checkbox" value="1" class="rounded border-zinc-300 text-teal-600" checked>
                            <span>{{ __('plans.visible_to_clinics') }}</span>
                        </label>
                        <div class="grid gap-2 lg:col-span-4 lg:grid-cols-2">
                            @foreach($serviceOptions as $serviceKey => $serviceLabel)
                                <label class="text-xs font-bold text-zinc-600">
                                    <span>{{ $serviceLabel }}</span>
                                    <input name="service_credits[{{ $serviceKey }}]" type="number" min="0" value="{{ old('service_credits.'.$serviceKey, $serviceKey === 'hair_transplant' ? 100 : 0) }}" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.usage_limit') }}">
                                </label>
                            @endforeach
                        </div>
                        <input name="description" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 lg:col-span-3" placeholder="{{ __('plans.fields.description') }}">
                        <x-admin.button type="submit" icon="package">
                            <span>{{ __('plans.create') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            </div>
        </x-admin.card>

        <x-admin.card>
            <x-admin.list-filters
                class="mb-5 mt-0"
                preset-key="admin.plans.index"
                :search-placeholder="__('plans.search_placeholder')"
                :filters="[
                    ['name' => 'status', 'label' => __('plans.fields.status'), 'type' => 'select', 'placeholder' => __('plans.all_statuses'), 'options' => __('plans.statuses')],
                    ['name' => 'plan_type', 'label' => __('plans.fields.plan_type'), 'type' => 'select', 'placeholder' => __('plans.fields.plan_type'), 'options' => __('plans.types')],
                ]"
                :sorts="[
                    'created_at' => __('messages.created_at'),
                    'name' => __('plans.fields.name'),
                    'plan_type' => __('plans.fields.plan_type'),
                    'price' => __('plans.fields.price'),
                    'duration_days' => __('plans.fields.duration_days'),
                    'usage_limit' => __('plans.fields.usage_limit'),
                    'status' => __('plans.fields.status'),
                ]"
            />

            <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-[46rem] text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('plans.fields.name')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="plan_type" :label="__('plans.fields.plan_type')" /></th>
                            <th class="px-4 py-3">{{ __('plans.service_credits') }}</th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="price" :label="__('plans.fields.price')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="duration_days" :label="__('plans.fields.duration_days')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('plans.fields.status')" /></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($plans as $plan)
                            @php
                                $creditInputs = collect($plan->serviceCredits())->mapWithKeys(fn ($credit, $serviceKey) => ['service_credits['.$serviceKey.']' => $credit])->all();
                            @endphp
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        <label
                                            for="edit-plan-{{ $plan->id }}-toggle"
                                            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700"
                                            role="button"
                                            tabindex="0"
                                            data-table-action-item
                                            data-admin-modal-trigger="edit-plan-{{ $plan->id }}"
                                        >
                                            <x-admin.icon name="edit" class="h-4 w-4" />
                                            <span>{{ __('plans.edit') }}</span>
                                        </label>
                                        <x-admin.table.action-item
                                            :action="route('admin.system.plans.update', $plan)"
                                            method="PUT"
                                            icon="check"
                                            :inputs="[
                                                'name' => $plan->name,
                                                'description' => $plan->description,
                                                'plan_type' => $plan->plan_type->value,
                                                'service_key' => $plan->service_key,
                                                'price' => $plan->price,
                                                'currency' => $plan->currency,
                                                'duration_days' => $plan->duration_days,
                                                'usage_limit' => $plan->usage_limit,
                                                'is_public' => $plan->is_public ? 1 : 0,
                                                'status' => $plan->status === 'active' ? 'inactive' : 'active',
                                            ] + $creditInputs"
                                        >
                                            {{ $plan->status === 'active' ? __('plans.statuses.inactive') : __('plans.statuses.active') }}
                                        </x-admin.table.action-item>
                                        <x-admin.table.action-item :action="route('admin.system.plans.destroy', $plan)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('plans.delete') }}</x-admin.table.action-item>
                                    </x-admin.table.action-menu>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-bold text-zinc-950">{{ $plan->name }}</p>
                                    <p class="mt-1 max-w-md truncate text-xs text-zinc-500">{{ $plan->description ?: __('messages.none') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge tone="teal">{{ __('plans.types.'.$plan->plan_type->value) }}</x-admin.badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($plan->serviceCredits() as $serviceKey => $credit)
                                            <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs text-nowrap font-bold text-zinc-700">{{ $serviceOptions[$serviceKey] ?? $serviceKey }}: {{ number_format($credit) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($plan->price) }} {{ $plan->currency }}</td>
                                <td class="px-4 py-3">{{ $plan->duration_days ? number_format($plan->duration_days) : __('messages.none') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <x-admin.badge :tone="$plan->status === 'active' ? 'emerald' : 'zinc'">{{ __('plans.statuses.'.$plan->status) }}</x-admin.badge>
                                        <x-admin.badge :tone="$plan->is_public ? 'teal' : 'amber'">{{ $plan->is_public ? __('plans.visible_to_clinics') : __('plans.hidden_from_clinics') }}</x-admin.badge>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12">
                                    <x-admin.empty-state icon="package">{{ __('plans.empty') }}</x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($plans as $plan)
                    @php
                        $creditInputs = collect($plan->serviceCredits())->mapWithKeys(fn ($credit, $serviceKey) => ['service_credits['.$serviceKey.']' => $credit])->all();
                    @endphp
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $plan->name }}</p>
                                <p class="admin-mobile-list-card-subtitle">{{ $plan->description ?: __('messages.none') }}</p>
                            </div>
                            <x-admin.table.action-menu>
                                <label
                                    for="edit-plan-{{ $plan->id }}-toggle"
                                    class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700"
                                    role="button"
                                    tabindex="0"
                                    data-table-action-item
                                    data-admin-modal-trigger="edit-plan-{{ $plan->id }}"
                                >
                                    <x-admin.icon name="edit" class="h-4 w-4" />
                                    <span>{{ __('plans.edit') }}</span>
                                </label>
                                <x-admin.table.action-item
                                    :action="route('admin.system.plans.update', $plan)"
                                    method="PUT"
                                    icon="check"
                                    :inputs="[
                                        'name' => $plan->name,
                                        'description' => $plan->description,
                                        'plan_type' => $plan->plan_type->value,
                                        'service_key' => $plan->service_key,
                                        'price' => $plan->price,
                                        'currency' => $plan->currency,
                                        'duration_days' => $plan->duration_days,
                                        'usage_limit' => $plan->usage_limit,
                                        'is_public' => $plan->is_public ? 1 : 0,
                                        'status' => $plan->status === 'active' ? 'inactive' : 'active',
                                    ] + $creditInputs"
                                >
                                    {{ $plan->status === 'active' ? __('plans.statuses.inactive') : __('plans.statuses.active') }}
                                </x-admin.table.action-item>
                                <x-admin.table.action-item :action="route('admin.system.plans.destroy', $plan)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('plans.delete') }}</x-admin.table.action-item>
                            </x-admin.table.action-menu>
                        </div>

                        <dl class="admin-mobile-list-fields">
                            <x-admin.mobile-list.field :label="__('plans.fields.plan_type')">
                                <x-admin.badge tone="teal">{{ __('plans.types.'.$plan->plan_type->value) }}</x-admin.badge>
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('plans.fields.price')">
                                <span dir="ltr">{{ number_format($plan->price) }} {{ $plan->currency }}</span>
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('plans.fields.status')">
                                <div class="flex flex-wrap gap-1">
                                    <x-admin.badge :tone="$plan->status === 'active' ? 'emerald' : 'zinc'">{{ __('plans.statuses.'.$plan->status) }}</x-admin.badge>
                                    <x-admin.badge :tone="$plan->is_public ? 'teal' : 'amber'">{{ $plan->is_public ? __('plans.visible_to_clinics') : __('plans.hidden_from_clinics') }}</x-admin.badge>
                                </div>
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('plans.service_credits')">
                                <span class="truncate">{{ collect($plan->serviceCredits())->map(fn ($credit, $serviceKey) => ($serviceOptions[$serviceKey] ?? $serviceKey).': '.number_format($credit))->join('، ') ?: __('messages.none') }}</span>
                            </x-admin.mobile-list.field>
                        </dl>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="package">{{ __('plans.empty') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>

            @foreach($plans as $plan)
                <x-admin.modal
                    id="edit-plan-{{ $plan->id }}"
                    :label="__('plans.edit')"
                    :title="$plan->name"
                    icon="edit"
                    :open="$errors->any() && old('_form') === 'edit_plan_'.$plan->id"
                    :trigger="false"
                >
                    <form method="POST" action="{{ route('admin.system.plans.update', $plan) }}" class="grid gap-3 lg:grid-cols-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="edit_plan_{{ $plan->id }}">
                        <x-admin.validation-summary for="edit_plan_{{ $plan->id }}" class="lg:col-span-4" />
                        <input name="name" value="{{ old('name', $plan->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.name') }}">
                        <select name="plan_type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(__('plans.types') as $type => $label)
                                <option value="{{ $type }}" @selected(old('plan_type', $plan->plan_type->value) === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="price" type="number" value="{{ old('price', $plan->price) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.price') }}">
                        <input name="duration_days" type="number" value="{{ old('duration_days', $plan->duration_days) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.duration_days') }}">
                        <input name="currency" value="{{ old('currency', $plan->currency) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.currency') }}">
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(__('plans.statuses') as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', $plan->status) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="inline-flex h-11 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-bold text-zinc-700">
                            <input name="is_public" type="checkbox" value="1" class="rounded border-zinc-300 text-teal-600" @checked(old('is_public', $plan->is_public))>
                            <span>{{ __('plans.visible_to_clinics') }}</span>
                        </label>
                        <div class="grid gap-2 lg:col-span-4 lg:grid-cols-2">
                            @foreach($serviceOptions as $serviceKey => $serviceLabel)
                                <label class="text-xs font-bold text-zinc-600">
                                    <span>{{ $serviceLabel }}</span>
                                    <input name="service_credits[{{ $serviceKey }}]" type="number" min="0" value="{{ old('service_credits.'.$serviceKey, $plan->serviceCredits()[$serviceKey] ?? 0) }}" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.usage_limit') }}">
                                </label>
                            @endforeach
                        </div>
                        <input name="description" value="{{ old('description', $plan->description) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 lg:col-span-3" placeholder="{{ __('plans.fields.description') }}">
                        <x-admin.button type="submit" icon="check">
                            <span>{{ __('plans.update') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endforeach

            <x-admin.table.pagination-footer :paginator="$plans" />
        </x-admin.card>
    @else
        <x-admin.card class="mb-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700">
                        <x-admin.icon name="package" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold">{{ __('plans.available_plans') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('plans.clinic_hint') }}</p>
                    </div>
                </div>

                @if($activeSubscription)
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                        {{ __('plans.current_plan') }}: {{ $activeSubscription->plan?->name }}
                    </div>
                @endif
            </div>
        </x-admin.card>

        <x-admin.list-filters
            class="mb-5 mt-0"
            preset-key="admin.plans.clinic"
            :search-placeholder="__('plans.search_placeholder')"
            :filters="[
                ['name' => 'plan_type', 'label' => __('plans.fields.plan_type'), 'type' => 'select', 'placeholder' => __('plans.all_types'), 'options' => __('plans.types')],
            ]"
            :sorts="[
                'price' => __('plans.fields.price'),
                'name' => __('plans.fields.name'),
                'plan_type' => __('plans.fields.plan_type'),
                'duration_days' => __('plans.fields.duration_days'),
                'usage_limit' => __('plans.fields.usage_limit'),
            ]"
        />

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @forelse($plans as $plan)
                @php
                    $state = $planPurchaseStates[$plan->id] ?? ['can_purchase' => true, 'reason' => null];
                    $typeTone = $plan->isCreditTopUp() ? 'amber' : ($plan->isServiceAddOn() ? 'teal' : 'emerald');
                @endphp
                <section class="flex min-h-full flex-col rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-lg font-bold leading-7 text-zinc-950">{{ $plan->name }}</h2>
                            <p class="mt-1 text-sm leading-6 text-zinc-500">{{ $plan->description }}</p>
                        </div>
                        <x-admin.badge :tone="$typeTone">{{ __('plans.types.'.$plan->plan_type->value) }}</x-admin.badge>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="flex min-w-0 items-start gap-2 rounded-lg bg-zinc-50 p-3">
                            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-white text-teal-700 ring-1 ring-zinc-100">
                                <x-admin.icon name="credit-card" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0">
                                <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.price') }}</dt>
                                <dd class="mt-1 break-words font-bold leading-6 text-zinc-950">{{ number_format($plan->price) }} {{ $plan->currency }}</dd>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-start gap-2 rounded-lg bg-zinc-50 p-3">
                            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-white text-teal-700 ring-1 ring-zinc-100">
                                <x-admin.icon name="calendar" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0">
                                <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.duration_days') }}</dt>
                                <dd class="mt-1 break-words font-bold leading-6 text-zinc-950">{{ $plan->duration_days ? number_format($plan->duration_days) : __('messages.none') }}</dd>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-start gap-2 rounded-lg bg-zinc-50 p-3">
                            <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-white text-teal-700 ring-1 ring-zinc-100">
                                <x-admin.icon name="package" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0">
                                <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.usage_limit') }}</dt>
                                <dd class="mt-1 break-words font-bold leading-6 text-zinc-950">{{ number_format($plan->usage_limit) }}</dd>
                            </div>
                        </div>
                    </dl>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($plan->serviceCredits() as $serviceKey => $credit)
                            <span class="inline-flex max-w-full items-start gap-1.5 rounded-lg bg-teal-50 px-2.5 py-1.5 text-xs font-bold text-teal-700">
                                <x-admin.icon name="sparkles" class="mt-0.5 h-3.5 w-3.5" />
                                <span class="min-w-0 break-words leading-5">{{ $serviceOptions[$serviceKey] ?? $serviceKey }}: {{ number_format($credit) }}</span>
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-4">
                        @can('permission', 'billing.pay')
                            @if(! $state['can_purchase'])
                                <x-admin.button type="button" variant="subtle" size="sm" icon="lock" class="cursor-not-allowed text-zinc-500 hover:bg-zinc-100" disabled aria-disabled="true">
                                    <span>{{ __('plans.not_purchasable') }}</span>
                                </x-admin.button>
                                @if($state['reason'])
                                    <p class="text-xs font-bold leading-6 text-amber-700">{{ $state['reason'] }}</p>
                                @endif
                            @else
                                <form method="POST" action="{{ route('admin.plans.purchase', $plan) }}">
                                    @csrf
                                    <x-admin.button type="submit" variant="teal" size="sm" icon="credit-card">
                                        <span>{{ $plan->isCreditTopUp() ? __('plans.buy_top_up') : ($plan->isServiceAddOn() ? __('plans.buy_add_on') : ($activeSubscription ? __('plans.upgrade') : __('plans.purchase_or_upgrade'))) }}</span>
                                    </x-admin.button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </section>
            @empty
                <section class="rounded-lg border border-dashed border-zinc-200 bg-white px-4 py-12 text-center lg:col-span-2 xl:col-span-3">
                    <x-admin.empty-state icon="package">{{ __('plans.empty') }}</x-admin.empty-state>
                </section>
            @endforelse
        </div>

        <x-admin.table.pagination-footer :paginator="$plans" class="mt-5 rounded-lg border border-zinc-100" />
    @endif
@endsection
