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
                    <form method="POST" action="{{ route('admin.system.plans.store') }}" class="grid gap-3 md:grid-cols-4">
                        @csrf
                        <input type="hidden" name="_form" value="create_plan">
                        <x-admin.validation-summary for="create_plan" class="md:col-span-4" />
                        <input name="name" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.name') }}">
                        <input name="price" type="number" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.price') }}">
                        <input name="duration_days" type="number" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.duration_days') }}">
                        <input name="usage_limit" type="number" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('plans.fields.usage_limit') }}">
                        <input name="currency" value="IRR" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr" placeholder="{{ __('plans.fields.currency') }}">
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach(__('plans.statuses') as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="description" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('plans.fields.description') }}">
                        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                            <x-admin.icon name="package" class="h-4 w-4" />
                            <span>{{ __('plans.create') }}</span>
                        </button>
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
                ]"
                :sorts="[
                    'created_at' => __('messages.created_at'),
                    'name' => __('plans.fields.name'),
                    'price' => __('plans.fields.price'),
                    'duration_days' => __('plans.fields.duration_days'),
                    'usage_limit' => __('plans.fields.usage_limit'),
                    'status' => __('plans.fields.status'),
                ]"
            />

            <div data-admin-table-container class="overflow-visible rounded-lg border border-zinc-100">
                <table class="w-full min-w-[54rem] text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('plans.fields.name')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="price" :label="__('plans.fields.price')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="duration_days" :label="__('plans.fields.duration_days')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="usage_limit" :label="__('plans.fields.usage_limit')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="status" :label="__('plans.fields.status')" /></th>
                            <th class="px-4 py-3"><x-admin.table.sortable-heading sort="created_at" :label="__('messages.created_at')" /></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($plans as $plan)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        <x-admin.table.action-item
                                            :action="route('admin.system.plans.update', $plan)"
                                            method="PUT"
                                            icon="check"
                                            :inputs="[
                                                'name' => $plan->name,
                                                'description' => $plan->description,
                                                'price' => $plan->price,
                                                'currency' => $plan->currency,
                                                'duration_days' => $plan->duration_days,
                                                'usage_limit' => $plan->usage_limit,
                                                'status' => $plan->status === 'active' ? 'inactive' : 'active',
                                            ]"
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
                                <td class="px-4 py-3" dir="ltr">{{ number_format($plan->price) }} {{ $plan->currency }}</td>
                                <td class="px-4 py-3">{{ number_format($plan->duration_days) }}</td>
                                <td class="px-4 py-3">{{ number_format($plan->usage_limit) }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :tone="$plan->status === 'active' ? 'emerald' : 'zinc'">{{ __('plans.statuses.'.$plan->status) }}</x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-zinc-500">{{ $plan->created_at?->diffForHumans() }}</td>
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

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($plans as $plan)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                            <p class="mt-1 min-h-10 text-sm leading-6 text-zinc-500">{{ $plan->description }}</p>
                        </div>
                        <x-admin.badge tone="emerald">{{ __('plans.statuses.'.$plan->status) }}</x-admin.badge>
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.price') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->price) }}</dd></div>
                        <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.duration_days') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->duration_days) }}</dd></div>
                        <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.usage_limit') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->usage_limit) }}</dd></div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @can('permission', 'billing.pay')
                            @if($activeSubscription?->plan_id === $plan->id)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('plans.same_active_plan_blocked') }}</div>
                            @else
                                <form method="POST" action="{{ route('admin.plans.purchase', $plan) }}">
                                    @csrf
                                    <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white transition hover:bg-zinc-950">
                                        <x-admin.icon name="credit-card" class="h-4 w-4" />
                                        <span>{{ $activeSubscription ? __('plans.upgrade') : __('plans.purchase_or_upgrade') }}</span>
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </section>
            @empty
                <section class="rounded-lg border border-dashed border-zinc-200 bg-white px-4 py-12 text-center md:col-span-2 xl:col-span-3">
                    <x-admin.empty-state icon="package">{{ __('plans.empty') }}</x-admin.empty-state>
                </section>
            @endforelse
        </div>

        <x-admin.table.pagination-footer :paginator="$plans" class="mt-5 rounded-lg border border-zinc-100" />
    @endif
@endsection
