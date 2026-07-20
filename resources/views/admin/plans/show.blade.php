@extends('admin.layout')

@section('title', $plan->name)
@section('page_title', $plan->name)

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ $plan->description ?: __('messages.none') }}</p>
                </div>
                <x-admin.button :href="route('admin.plans.index')" variant="secondary" size="sm" icon="package">
                    <span>{{ __('plans.title') }}</span>
                </x-admin.button>
            </div>

            <dl class="mt-5 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.plan_type') }}</dt>
                    <dd class="mt-1"><x-admin.badge tone="teal">{{ __('plans.types.'.$plan->plan_type->value) }}</x-admin.badge></dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('plans.credit_amount') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($plan->usage_limit) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.price') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($plan->price) }} {{ $plan->currency }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.duration_days') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ $plan->duration_days ? number_format($plan->duration_days) : __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.status') }}</dt>
                    <dd class="mt-1"><x-admin.badge :tone="$plan->status === 'active' ? 'emerald' : 'zinc'">{{ __('plans.statuses.'.$plan->status) }}</x-admin.badge></dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('plans.fields.visibility') }}</dt>
                    <dd class="mt-1"><x-admin.badge :tone="$plan->is_public ? 'teal' : 'amber'">{{ $plan->is_public ? __('plans.visible_to_clinics') : __('plans.hidden_from_clinics') }}</x-admin.badge></dd>
                </div>
            </dl>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('plans.assigned_services') }}</h3>
            <div class="mt-4 flex flex-wrap gap-2">
	                @foreach($plan->serviceKeys() as $serviceKey)
	                    <span class="inline-flex max-w-full items-center gap-2 rounded-lg bg-teal-50 px-3 py-2 text-sm font-bold text-teal-700">
	                        <x-admin.category-icon :icon="$serviceIcons[$serviceKey] ?? null" class="h-4 w-4" />
	                        <span>{{ $serviceOptions[$serviceKey] ?? $serviceKey }}</span>
	                    </span>
	                @endforeach
            </div>
        </x-admin.card>

        @if($canManagePlans && ! $plan->isCreditTopUp())
            <x-admin.card>
                <div>
                    <h3 class="text-base font-bold">{{ __('plans.assigned_models') }}</h3>
                    <p class="mt-1 text-xs leading-5 text-zinc-500">{{ __('plans.assigned_models_detail_hint') }}</p>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @forelse($plan->aiModelAccesses->groupBy('usage_category_key') as $serviceKey => $accesses)
                        <section class="rounded-lg border border-zinc-200 bg-zinc-50/70 p-3">
                            <div class="flex items-center gap-2">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-teal-700 ring-1 ring-zinc-100">
                                    <x-admin.category-icon :icon="$serviceIcons[$serviceKey] ?? null" class="h-4 w-4" />
                                </span>
                                <p class="text-sm font-bold text-zinc-900">{{ $serviceOptions[$serviceKey] ?? $serviceKey }}</p>
                            </div>

                            <div class="mt-3 space-y-2">
                                @foreach($accesses as $access)
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2.5">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-zinc-900">{{ $access->name }}</p>
                                            <p class="mt-0.5 text-xs text-zinc-500" dir="ltr">{{ $access->model_key }}</p>
                                        </div>
                                        <x-admin.badge :tone="$access->is_active ? 'emerald' : 'zinc'">
                                            {{ __('ai_models.statuses.'.($access->is_active ? 'active' : 'inactive')) }}
                                        </x-admin.badge>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm font-bold text-amber-800 md:col-span-2">
                            {{ __('plans.no_models_for_service') }}
                        </p>
                    @endforelse
                </div>
            </x-admin.card>
        @endif
    </div>
@endsection
