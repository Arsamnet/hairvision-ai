@extends('admin.layout')

@section('title', __('plans.title'))
@section('page_title', __('plans.title'))

@section('content')
    @if($canManagePlans)
        <section class="mb-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <div class="flex items-start gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-zinc-950 text-white">
                    <x-admin.icon name="sparkles" />
                </span>
                <div>
                    <h2 class="text-lg font-bold">{{ __('plans.new_plan') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('plans.management_hint') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.system.plans.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
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
        </section>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($plans as $plan)
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                        <p class="mt-1 min-h-10 text-sm leading-6 text-zinc-500">{{ $plan->description }}</p>
                    </div>
                    <span class="rounded-full {{ $plan->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-500' }} px-3 py-1 text-xs font-bold">{{ __('plans.statuses.'.$plan->status) }}</span>
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.price') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->price) }}</dd></div>
                    <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.duration_days') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->duration_days) }}</dd></div>
                    <div class="rounded-lg bg-zinc-50 p-3"><dt class="text-zinc-500">{{ __('plans.fields.usage_limit') }}</dt><dd class="mt-1 font-bold">{{ number_format($plan->usage_limit) }}</dd></div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @can('permission', 'billing.pay')
                        @if($plan->status === 'active')
                            <form method="POST" action="{{ route('admin.plans.purchase', $plan) }}">
                                @csrf
                                <button class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-zinc-950">
                                    <x-admin.icon name="credit-card" class="h-4 w-4" />
                                    <span>{{ __('plans.purchase_or_upgrade') }}</span>
                                </button>
                            </form>
                        @endif
                    @endcan

                    @if($canManagePlans)
                        <form method="POST" action="{{ route('admin.system.plans.update', $plan) }}" class="contents">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="name" value="{{ $plan->name }}">
                            <input type="hidden" name="description" value="{{ $plan->description }}">
                            <input type="hidden" name="price" value="{{ $plan->price }}">
                            <input type="hidden" name="currency" value="{{ $plan->currency }}">
                            <input type="hidden" name="duration_days" value="{{ $plan->duration_days }}">
                            <input type="hidden" name="usage_limit" value="{{ $plan->usage_limit }}">
                            <input type="hidden" name="status" value="{{ $plan->status === 'active' ? 'inactive' : 'active' }}">
                            <button class="rounded-lg border border-zinc-200 px-3 py-2 text-xs font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700">{{ $plan->status === 'active' ? __('plans.statuses.inactive') : __('plans.statuses.active') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.system.plans.destroy', $plan) }}">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-50">{{ __('plans.delete') }}</button>
                        </form>
                    @endif
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
