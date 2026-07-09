@php
    $access = $access ?? null;
    $models = $models ?? [];
    $usageCategories = $usageCategories ?? [];
    $formKey = $formKey ?? 'ai_model_access_form';
    $returnToIndex = $returnToIndex ?? false;
    $selectedModel = old('model_key', $access?->model_key ?? array_key_first($models));
    $selectedCategory = old('usage_category_key', $access?->usage_category_key ?? array_key_first($usageCategories));
    $credentialFields = collect($models)
        ->flatMap(fn (array $model): array => array_merge($model['required_credentials'] ?? [], $model['optional_credentials'] ?? []))
        ->unique()
        ->values();
    $settingFields = collect($models)
        ->flatMap(fn (array $model): array => $model['configurable_settings'] ?? [])
        ->unique()
        ->values();
    $generationStages = app(\App\Services\Generation\GenerationStageCatalog::class)->publicStages();
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
@if($returnToIndex)
    <input type="hidden" name="return_to_index" value="1">
@endif

<x-admin.validation-summary :for="$formKey" class="md:col-span-2" />

<div class="grid gap-3 md:grid-cols-2">
    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('ai_models.model') }}</span>
        <select name="model_key" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach($models as $key => $model)
                <option value="{{ $key }}" @selected($selectedModel === $key)>{{ $resolver->modelLabel($key) }}</option>
            @endforeach
        </select>
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('ai_models.usage_category') }}</span>
        <select name="usage_category_key" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach($usageCategories as $key => $category)
                <option value="{{ $key }}" @selected($selectedCategory === $key)>{{ $resolver->usageCategoryLabel($key) }}</option>
            @endforeach
        </select>
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('system.name') }}</span>
        <input name="name" value="{{ old('name', $access?->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('ai_models.priority') }}</span>
        <input name="priority" value="{{ old('priority', $access?->priority) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.priority') }}" dir="ltr">
    </label>

    <label class="grid gap-1 text-sm font-bold md:col-span-2">
        <span>{{ __('system.description') }}</span>
        <textarea name="description" rows="2" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.description') }}">{{ old('description', $access?->description) }}</textarea>
    </label>

    @if($credentialFields->isNotEmpty())
        <div class="grid gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 md:col-span-2 md:grid-cols-2">
            <p class="text-sm font-bold text-zinc-700 md:col-span-2">{{ __('ai_models.credentials') }}</p>
            @foreach($credentialFields as $field)
                <input
                    name="credentials[{{ $field }}]"
                    value="{{ old('credentials.'.$field) }}"
                    class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500"
                    placeholder="{{ __('ai_models.'.$field) }} - {{ __('ai_models.credential_placeholder') }}"
                    dir="ltr"
                    autocomplete="off"
                >
            @endforeach
        </div>
    @endif

    @if($settingFields->isNotEmpty())
        <div class="grid gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 md:col-span-2 md:grid-cols-2">
            <p class="text-sm font-bold text-zinc-700 md:col-span-2">{{ __('ai_models.settings') }}</p>
            @foreach($settingFields as $field)
                <input
                    name="settings[{{ $field }}]"
                    value="{{ old('settings.'.$field, data_get($access?->settings ?? [], $field)) }}"
                    class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500"
                    placeholder="{{ __('ai_models.'.$field) }} - {{ __('ai_models.setting_placeholder') }}"
                    dir="ltr"
                    autocomplete="off"
                >
            @endforeach
        </div>
    @endif

    <label class="grid gap-1 text-sm font-bold md:col-span-2">
        <span>{{ __('ai_models.prompt') }}</span>
        <textarea name="prompt" rows="4" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500" placeholder="{{ __('ai_models.prompt') }}">{{ old('prompt', $access?->prompt) }}</textarea>
    </label>

    <div class="grid gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 md:col-span-2">
        <div>
            <p class="text-sm font-bold text-zinc-700">{{ __('ai_models.stage_prompts') }}</p>
            <p class="mt-1 text-xs leading-6 text-zinc-500">{{ __('ai_models.stage_prompt_hint') }}</p>
        </div>
        @foreach($generationStages as $stage)
            <label class="grid gap-1 text-sm font-bold">
                <span>{{ $stage['label'] }}</span>
                <textarea
                    name="settings[stage_prompts][{{ $stage['key'] }}]"
                    rows="5"
                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500"
                    dir="ltr"
                    placeholder="{{ $stage['label'] }}"
                >{{ old('settings.stage_prompts.'.$stage['key'], data_get($access?->settings ?? [], 'stage_prompts.'.$stage['key'])) }}</textarea>
            </label>
        @endforeach
    </div>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('ai_models.credit_limit') }}</span>
        <input name="credit_limit" value="{{ old('credit_limit', $access?->credit_limit) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.unlimited_credit') }}" dir="ltr">
    </label>

    <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $access?->is_active ?? true))>
        <span>{{ __('ai_models.statuses.active') }}</span>
    </label>

    <x-admin.button type="submit" icon="sparkles" class="md:col-span-2">
        <span>{{ __('system.submit') }}</span>
    </x-admin.button>
</div>
