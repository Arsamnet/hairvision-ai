@extends('admin.layout')

@section('title', __('plans.title'))
@section('page_title', __('plans.title'))

@section('content')
    @if($canManagePlans)
        <x-admin.card class="mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('plans.new_plan') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('plans.management_hint') }}</p>
                </div>
                <x-admin.modal id="create-plan-modal" :label="__('plans.new_plan')" :title="__('plans.new_plan')" icon="sparkles" :open="$errors->any() && old('_form') === 'create_plan'">
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
                            <x-admin.icon name="sparkles" class="h-4 w-4" />
                            <span>{{ __('plans.create') }}</span>
                        </button>
                    </form>
                </x-admin.modal>
            </div>
        </x-admin.card>
    @endif

    <x-admin.card class="mb-5">
        <x-admin.list-filters
            class="mt-0"
            preset-key="admin.plans.index"
            :search-placeholder="__('plans.search_placeholder')"
            :filters="$canManagePlans ? [
                ['name' => 'status', 'label' => __('plans.fields.status'), 'type' => 'select', 'placeholder' => __('plans.all_statuses'), 'options' => __('plans.statuses')],
            ] : []"
            :sorts="[
                'price' => __('plans.fields.price'),
                'created_at' => __('messages.created_at'),
                'name' => __('plans.fields.name'),
                'duration_days' => __('plans.fields.duration_days'),
                'usage_limit' => __('plans.fields.usage_limit'),
                'status' => __('plans.fields.status'),
            ]"
        />
    </x-admin.card>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($plans as $plan)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                        <p class="mt-1 min-h-10 text-sm leading-6 text-zinc-500">{{ $plan->description }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full {{ $plan->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-500' }} px-3 py-1 text-xs font-bold">{{ __('plans.statuses.'.$plan->status) }}</span>
                        @if($canManagePlans)
                            <x-admin.table.action-menu>
                                <x-admin.table.action-item
                                    :action="route('admin.system.plans.update', $plan)"
                                    method="PUT"
                                    icon="edit"
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
                        @endif
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.price') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->price) }}</dd></div>
                    <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.duration_days') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->duration_days) }}</dd></div>
                    <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.usage_limit') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->usage_limit) }}</dd></div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @can('permission', 'billing.pay')
                        @if($plan->status === 'active')
                            @if($activeSubscription?->plan_id === $plan->id)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('plans.same_active_plan_blocked') }}</div>
                            @else
                                <form method="POST" action="{{ route('admin.plans.purchase', $plan) }}">
                                    @csrf
                                    <button class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-zinc-950">
                                        <x-admin.icon name="credit-card" class="h-4 w-4" />
                                        <span>{{ $activeSubscription ? __('plans.upgrade') : __('plans.purchase_or_upgrade') }}</span>
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endcan

                </div>
            </section>
        @empty
            <section class="rounded-lg border border-dashed border-zinc-200 bg-white px-4 py-12 text-center md:col-span-2 xl:col-span-3">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-lg bg-zinc-50 text-zinc-400">
                    <x-admin.icon name="sparkles" />
                </span>
                <p class="mt-3 text-sm font-bold text-zinc-500">{{ __('plans.empty') }}</p>
            </section>
        @endforelse
    </div>
    <div class="mt-5">{{ $plans->links() }}</div>
@endsection
