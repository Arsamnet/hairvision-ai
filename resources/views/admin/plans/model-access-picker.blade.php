@php
    $selectedModelAccessIds = collect($selectedModelAccessIds ?? [])->map(fn ($id) => (int) $id)->all();
    $selectedServiceKeys = collect($selectedServiceKeys ?? [])->map(fn ($key) => (string) $key)->all();
    $isCreditTopUp = ($selectedPlanType ?? null) === \App\Enums\PlanType::CreditTopUp->value;
@endphp

<fieldset
    class="lg:col-span-4"
    data-plan-model-picker
    @if($isCreditTopUp) hidden @endif
>
    <legend class="text-sm font-bold text-zinc-800">{{ __('plans.assigned_models') }}</legend>
    <p class="mt-1 text-xs leading-5 text-zinc-500">{{ __('plans.assigned_models_hint') }}</p>

    <div class="mt-3 grid gap-3 xl:grid-cols-2">
        @foreach($serviceOptions as $serviceKey => $serviceLabel)
            @php
                $models = $modelAccessOptions->get($serviceKey, collect());
                $serviceSelected = in_array($serviceKey, $selectedServiceKeys, true);
                $selectedModelAccessId = $models
                    ->first(fn ($access) => in_array((int) $access->id, $selectedModelAccessIds, true))
                    ?->id;
            @endphp
            <section
                class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm"
                data-plan-model-group="{{ $serviceKey }}"
                @if(! $serviceSelected || $isCreditTopUp) hidden @endif
            >
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 bg-zinc-50/70 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-teal-700 ring-1 ring-zinc-100">
                            <x-admin.category-icon :icon="$serviceIcons[$serviceKey] ?? null" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-zinc-900">{{ $serviceLabel }}</p>
                            <p class="truncate text-xs text-zinc-500" dir="ltr">{{ $serviceKey }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-bold text-teal-700">
                        {{ __('plans.single_model') }}
                    </span>
                </div>

                @if($models->isEmpty())
                    <p class="m-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-bold leading-5 text-amber-800">
                        {{ __('plans.no_models_for_service') }}
                    </p>
                @else
                    <label class="block p-4">
                        <span class="mb-2 block text-xs font-bold text-zinc-700">{{ __('plans.select_model') }}</span>
                        <select
                            name="ai_model_access_ids[]"
                            class="h-11 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm font-bold text-zinc-800 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100"
                            data-plan-model
                            @disabled(! $serviceSelected || $isCreditTopUp)
                        >
                            <option value="">{{ __('plans.select_model_placeholder') }}</option>
                            @foreach($models as $access)
                                <option value="{{ $access->id }}" @selected((int) $selectedModelAccessId === (int) $access->id)>
                                    {{ $access->name }} ({{ $access->model_key }})
                                </option>
                            @endforeach
                        </select>
                        <span class="mt-2 block text-[11px] leading-5 text-zinc-500">{{ __('plans.select_model_hint') }}</span>
                    </label>
                @endif
            </section>
        @endforeach
    </div>
</fieldset>
