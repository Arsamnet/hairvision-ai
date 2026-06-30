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

    <input name="name" value="{{ old('name', $access?->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
    <input name="priority" value="{{ old('priority', $access?->priority) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.priority') }}" dir="ltr">

    <textarea name="description" rows="2" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('system.description') }}">{{ old('description', $access?->description) }}</textarea>

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

    <textarea name="prompt" rows="4" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('ai_models.prompt') }}">{{ old('prompt', $access?->prompt) }}</textarea>

    <input name="credit_limit" value="{{ old('credit_limit', $access?->credit_limit) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.credit_limit') }}" dir="ltr">

    <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $access?->is_active ?? true))>
        <span>{{ __('ai_models.statuses.active') }}</span>
    </label>

    <x-admin.button type="submit" icon="sparkles" class="md:col-span-2">
        <span>{{ __('system.submit') }}</span>
    </x-admin.button>
</div>
