@extends('admin.layout')

@section('title', __('plans.title'))
@section('page_title', __('plans.title'))

@section('content')
    @if($canManagePlans)
        <x-admin.card class="mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('plans.title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('plans.management_hint') }}</p>
                </div>
                <x-admin.modal id="create-plan-modal" :label="__('plans.new_plan')" :title="__('plans.new_plan')" icon="package" :open="$errors->any() && old('_form') === 'create_plan'">
                    <form method="POST" action="{{ route('admin.system.plans.store') }}" class="grid gap-3 lg:grid-cols-4" data-plan-form>
                        @csrf
                        <input type="hidden" name="_form" value="create_plan">
                        <x-admin.validation-summary for="create_plan" class="lg:col-span-4" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.name') }}">
                        <select name="plan_type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" data-plan-type>
                            @foreach(\App\Support\StateCatalog::options('plans.types') as $type => $label)
                                <option value="{{ $type }}" @selected((old('_form') === 'create_plan' ? old('plan_type', \App\Enums\PlanType::BaseSubscription->value) : \App\Enums\PlanType::BaseSubscription->value) === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="price" type="text" inputmode="numeric" data-number-input class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.price') }}" dir="ltr">
                        <input name="duration_days" type="text" inputmode="numeric" data-number-input class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.duration_days') }}" dir="ltr">
                        <input name="usage_limit" type="text" inputmode="numeric" data-number-input min="1" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.credit_amount') }}" dir="ltr">
                        <input name="currency" value="ریال" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.currency') }}">
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(\App\Support\StateCatalog::options('plans.statuses') as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="inline-flex h-11 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-bold text-zinc-700">
                            <input name="is_public" type="checkbox" value="1" class="rounded border-zinc-300 text-teal-600" checked>
                            <span>{{ __('plans.visible_to_clinics') }}</span>
                        </label>
	                        <fieldset class="lg:col-span-4">
	                            <legend class="mb-2 text-sm font-bold text-zinc-700">{{ __('plans.assigned_services') }}</legend>
	                            @php
	                                $selectedServiceKeys = old('_form') === 'create_plan'
	                                    ? old('service_keys', ['hair_transplant'])
	                                    : ['hair_transplant'];
	                            @endphp
	                            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
	                            @foreach($serviceOptions as $serviceKey => $serviceLabel)
	                                <label class="flex min-h-16 cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-3 text-sm transition has-checked:border-teal-500 has-checked:bg-teal-50">
	                                    <input name="service_keys[]" type="checkbox" value="{{ $serviceKey }}" class="mt-1 rounded border-zinc-300 text-teal-600 focus:ring-teal-500" data-plan-service @checked(in_array($serviceKey, $selectedServiceKeys, true))>
	                                    <span class="flex min-w-0 items-start gap-3">
		                                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-zinc-50 text-teal-700 ring-1 ring-zinc-100">
		                                            <x-admin.category-icon :icon="$serviceIcons[$serviceKey] ?? null" class="h-4 w-4" />
		                                        </span>
	                                        <span class="min-w-0">
	                                            <span class="block font-bold text-zinc-800">{{ $serviceLabel }}</span>
                                            <span class="mt-1 block truncate text-xs font-bold text-zinc-500" dir="ltr">{{ $serviceKey }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                            </div>
                        </fieldset>
                        @php
                            $selectedPlanType = old('_form') === 'create_plan'
                                ? old('plan_type', \App\Enums\PlanType::BaseSubscription->value)
                                : \App\Enums\PlanType::BaseSubscription->value;
                            $selectedModelAccessIds = old('_form') === 'create_plan'
                                ? old('ai_model_access_ids', [])
                                : [];
                        @endphp
                        @include('admin.plans.model-access-picker')
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
                    ['name' => 'status', 'label' => __('plans.fields.status'), 'type' => 'select', 'placeholder' => __('plans.all_statuses'), 'options' => \App\Support\StateCatalog::options('plans.statuses')],
                    ['name' => 'plan_type', 'label' => __('plans.fields.plan_type'), 'type' => 'select', 'placeholder' => __('plans.fields.plan_type'), 'options' => \App\Support\StateCatalog::options('plans.types')],
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
                <table class="w-full min-w-184 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('plans.fields.name')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="plan_type" :label="__('plans.fields.plan_type')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="usage_limit" :label="__('plans.credit_amount')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="price" :label="__('plans.fields.price')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="duration_days" :label="__('plans.fields.duration_days')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('plans.fields.status')" /></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($plans as $plan)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        <x-admin.table.action-item :href="route('admin.plans.show', $plan)" icon="eye">{{ __('messages.view') }}</x-admin.table.action-item>
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
                                <td class="px-4 py-3" dir="ltr">{{ number_format($plan->usage_limit) }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($plan->price) }} {{ $plan->currency }}</td>
                                <td class="px-4 py-3">{{ $plan->duration_days ? number_format($plan->duration_days) : __('messages.none') }}</td>
	                                <td class="px-4 py-3">
	                                    <div class="flex flex-wrap gap-1">
	                                        <x-admin.badge :tone="$plan->status === 'active' ? 'emerald' : 'zinc'">{{ __('plans.statuses.'.$plan->status) }}</x-admin.badge>
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
                        $serviceKeys = $plan->serviceKeys();
                    @endphp
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $plan->name }}</p>
                                <p class="admin-mobile-list-card-subtitle">{{ $plan->description ?: __('messages.none') }}</p>
                            </div>
                            <x-admin.table.action-menu>
                                <x-admin.table.action-item :href="route('admin.plans.show', $plan)" icon="eye">{{ __('messages.view') }}</x-admin.table.action-item>
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
                            <x-admin.mobile-list.field :label="__('plans.assigned_services')">
                                <span class="truncate">{{ collect($serviceKeys)->map(fn ($serviceKey) => $serviceOptions[$serviceKey] ?? $serviceKey)->join('، ') ?: __('messages.none') }}</span>
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('plans.credit_amount')">{{ number_format($plan->usage_limit) }}</x-admin.mobile-list.field>
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
                    <form method="POST" action="{{ route('admin.system.plans.update', $plan) }}" class="grid gap-3 lg:grid-cols-4" data-plan-form>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="edit_plan_{{ $plan->id }}">
                        <x-admin.validation-summary for="edit_plan_{{ $plan->id }}" class="lg:col-span-4" />
                        <input name="name" value="{{ old('name', $plan->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.name') }}">
                        <select name="plan_type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" data-plan-type>
                            @foreach(\App\Support\StateCatalog::options('plans.types') as $type => $label)
                                <option value="{{ $type }}" @selected(old('plan_type', $plan->plan_type->value) === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="price" type="text" inputmode="numeric" data-number-input value="{{ old('price', $plan->price) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.price') }}" dir="ltr">
                        <input name="duration_days" type="text" inputmode="numeric" data-number-input value="{{ old('duration_days', $plan->duration_days) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.duration_days') }}" dir="ltr">
                        <input name="usage_limit" type="text" inputmode="numeric" data-number-input min="1" value="{{ old('usage_limit', $plan->usage_limit) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.credit_amount') }}" dir="ltr">
                        <input name="currency" value="{{ old('currency', $plan->currency) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.currency') }}">
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(\App\Support\StateCatalog::options('plans.statuses') as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', $plan->status) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="inline-flex h-11 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-bold text-zinc-700">
                            <input name="is_public" type="checkbox" value="1" class="rounded border-zinc-300 text-teal-600" @checked(old('is_public', $plan->is_public))>
                            <span>{{ __('plans.visible_to_clinics') }}</span>
                        </label>
	                        <fieldset class="lg:col-span-4">
	                            <legend class="mb-2 text-sm font-bold text-zinc-700">{{ __('plans.assigned_services') }}</legend>
	                            @php
	                                $selectedServiceKeys = old('_form') === 'edit_plan_'.$plan->id
	                                    ? old('service_keys', $plan->serviceKeys())
	                                    : $plan->serviceKeys();
	                            @endphp
	                            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
	                            @foreach($serviceOptions as $serviceKey => $serviceLabel)
	                                <label class="flex min-h-16 cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-3 text-sm transition has-checked:border-teal-500 has-checked:bg-teal-50">
	                                    <input name="service_keys[]" type="checkbox" value="{{ $serviceKey }}" class="mt-1 rounded border-zinc-300 text-teal-600 focus:ring-teal-500" data-plan-service @checked(in_array($serviceKey, $selectedServiceKeys, true))>
	                                    <span class="flex min-w-0 items-start gap-3">
		                                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-zinc-50 text-teal-700 ring-1 ring-zinc-100">
		                                            <x-admin.category-icon :icon="$serviceIcons[$serviceKey] ?? null" class="h-4 w-4" />
		                                        </span>
	                                        <span class="min-w-0">
	                                            <span class="block font-bold text-zinc-800">{{ $serviceLabel }}</span>
                                            <span class="mt-1 block truncate text-xs font-bold text-zinc-500" dir="ltr">{{ $serviceKey }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                            </div>
                        </fieldset>
                        @php
                            $isCurrentEditForm = old('_form') === 'edit_plan_'.$plan->id;
                            $selectedPlanType = $isCurrentEditForm
                                ? old('plan_type', $plan->plan_type->value)
                                : $plan->plan_type->value;
                            $selectedModelAccessIds = $isCurrentEditForm
                                ? old('ai_model_access_ids', $plan->aiModelAccesses->pluck('id')->all())
                                : $plan->aiModelAccesses->pluck('id')->all();
                        @endphp
                        @include('admin.plans.model-access-picker')
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
                ['name' => 'plan_type', 'label' => __('plans.fields.plan_type'), 'type' => 'select', 'placeholder' => __('plans.all_types'), 'options' => \App\Support\StateCatalog::options('plans.types')],
            ]"
            :sorts="[
                'price' => __('plans.fields.price'),
                'name' => __('plans.fields.name'),
                'plan_type' => __('plans.fields.plan_type'),
                'duration_days' => __('plans.fields.duration_days'),
                'usage_limit' => __('plans.fields.usage_limit'),
            ]"
        />

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @forelse($plans as $plan)
                @php
                    $state = $planPurchaseStates[$plan->id] ?? ['can_purchase' => true, 'reason' => null];
                    $typeTone = $plan->isCreditTopUp() ? 'amber' : ($plan->isServiceAddOn() ? 'teal' : 'emerald');
                    $isCurrentPlan = $activeSubscription?->plan_id === $plan->id;
                    $cardClasses = $isCurrentPlan
                        ? 'border-emerald-300 bg-emerald-50/80 ring-emerald-200'
                        : 'border-zinc-200 bg-white ring-zinc-200';
                    $metricClasses = $isCurrentPlan
                        ? 'bg-white/85 text-emerald-950 ring-emerald-100'
                        : 'bg-zinc-50 text-zinc-950 ring-zinc-100';
                @endphp
                <section
                    class="flex min-h-full flex-col rounded-lg border p-4 shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md {{ $cardClasses }}"
                    @if($isCurrentPlan) data-current-plan="true" @endif
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="wrap-break-word text-base font-bold leading-6 text-zinc-950">{{ $plan->name }}</h2>
                            @if($plan->description)
                                <p class="mt-1 line-clamp-2 text-xs leading-5 {{ $isCurrentPlan ? 'text-emerald-800' : 'text-zinc-500' }}">{{ $plan->description }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            @if($isCurrentPlan)
                                <x-admin.badge tone="emerald">{{ __('plans.current_plan') }}</x-admin.badge>
                            @endif
                            <x-admin.badge :tone="$typeTone">{{ __('plans.types.'.$plan->plan_type->value) }}</x-admin.badge>
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="min-w-0 rounded-lg px-2.5 py-2 ring-1 {{ $metricClasses }}">
                            <dt class="font-bold text-zinc-500">{{ __('plans.fields.price') }}</dt>
                            <dd class="mt-1 wrap-break-word font-bold leading-5">{{ number_format($plan->price) }} {{ $plan->currency }}</dd>
                        </div>
                        <div class="min-w-0 rounded-lg px-2.5 py-2 ring-1 {{ $metricClasses }}">
                            <dt class="font-bold text-zinc-500">{{ __('plans.fields.duration_days') }}</dt>
                            <dd class="mt-1 wrap-break-word font-bold leading-5">{{ $plan->duration_days ? number_format($plan->duration_days) : __('messages.none') }}</dd>
                        </div>
                        <div class="min-w-0 rounded-lg px-2.5 py-2 ring-1 {{ $metricClasses }}">
                            <dt class="font-bold text-zinc-500">{{ __('plans.fields.usage_limit') }}</dt>
                            <dd class="mt-1 wrap-break-word font-bold leading-5">{{ number_format($plan->usage_limit) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($plan->serviceKeys() as $serviceKey)
                            <span class="inline-flex max-w-full items-start gap-1.5 rounded-lg px-2 py-1 text-xs font-bold {{ $isCurrentPlan ? 'bg-emerald-100 text-emerald-800' : 'bg-teal-50 text-teal-700' }}">
	                                <x-admin.category-icon :icon="$serviceIcons[$serviceKey] ?? null" class="mt-0.5 h-3 w-3" />
                                <span class="min-w-0 wrap-break-word leading-5">{{ $serviceOptions[$serviceKey] ?? $serviceKey }}</span>
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-3">
                        <x-admin.button :href="route('admin.plans.show', $plan)" variant="secondary" size="compact-sm" icon="eye" icon-class="h-3.5 w-3.5">
                            <span>{{ __('messages.view') }}</span>
                        </x-admin.button>

                        @can('permission', 'billing.pay')
                            @if(! $state['can_purchase'])
                                <x-admin.button type="button" variant="subtle" size="compact-sm" icon="lock" icon-class="h-3.5 w-3.5" class="cursor-not-allowed text-zinc-500 hover:bg-zinc-100" disabled aria-disabled="true">
                                    <span>{{ __('plans.not_purchasable') }}</span>
                                </x-admin.button>
                                @if($state['reason'])
                                    <p class="basis-full text-xs font-bold leading-5 {{ $isCurrentPlan ? 'text-emerald-800' : 'text-amber-700' }}">{{ $state['reason'] }}</p>
                                @endif
                            @else
                                <form method="POST" action="{{ route('admin.plans.purchase', $plan) }}">
                                    @csrf
                                    <x-admin.button type="submit" variant="teal" size="compact-sm" icon="credit-card" icon-class="h-3.5 w-3.5">
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
