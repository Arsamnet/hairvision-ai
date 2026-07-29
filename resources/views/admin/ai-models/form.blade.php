@php
    $access = $access ?? null;
    $models = $models ?? [];
    $usageCategories = $usageCategories ?? [];
    $formKey = $formKey ?? 'ai_model_access_form';
    $returnToIndex = $returnToIndex ?? false;
    $inputImageDefaults = \App\Services\AI\InputImageProcessingSettings::defaults();
    $selectedModel = old('model_key', $access?->model_key ?? array_key_first($models));
    $selectedCategory = old('usage_category_key', $access?->usage_category_key ?? array_key_first($usageCategories));
    $inputImageProcessingEnabled = filter_var(
        old(
            'settings.input_image_processing_enabled',
            $access
                ? data_get($access->settings ?? [], 'input_image_processing_enabled', false)
                : $inputImageDefaults['input_image_processing_enabled'],
        ),
        FILTER_VALIDATE_BOOLEAN,
    );
    $credentialFields = collect($models)
        ->flatMap(fn (array $model): array => array_merge($model['required_credentials'] ?? [], $model['optional_credentials'] ?? []))
        ->unique()
        ->values();
    $settingFields = collect($models)
        ->flatMap(fn (array $model): array => $model['configurable_settings'] ?? [])
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

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('system.name') }}</span>
        <input name="name" value="{{ old('name', $access?->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
    </label>

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('ai_models.priority') }}</span>
        <input name="priority" type="text" inputmode="numeric" data-number-input value="{{ old('priority', $access?->priority) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.priority') }}" dir="ltr">
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

    <div data-input-image-processing class="grid gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 md:col-span-2 md:grid-cols-2">
        <div class="md:col-span-2">
            <p class="text-sm font-bold text-zinc-700">{{ __('ai_models.input_image_processing_title') }}</p>
            <p class="mt-1 text-xs leading-6 text-zinc-500">{{ __('ai_models.input_image_processing_hint') }}</p>
        </div>

        <label class="flex h-11 items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-bold md:col-span-2">
            <input type="hidden" name="settings[input_image_processing_enabled]" value="0">
            <input
                data-input-image-processing-toggle
                type="checkbox"
                name="settings[input_image_processing_enabled]"
                value="1"
                @checked($inputImageProcessingEnabled)
            >
            <span>{{ __('ai_models.input_image_processing_enabled') }}</span>
        </label>

        <label data-input-image-processing-field class="grid gap-1 text-sm font-bold">
            <span>{{ __('ai_models.input_image_max_dimension') }}</span>
            <input
                name="settings[input_image_max_dimension]"
                type="text"
                inputmode="numeric"
                data-number-input
                value="{{ old('settings.input_image_max_dimension', $access ? data_get($access->settings ?? [], 'input_image_max_dimension') : $inputImageDefaults['input_image_max_dimension']) }}"
                class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500"
                placeholder="{{ __('ai_models.input_image_max_dimension_placeholder') }}"
                dir="ltr"
            >
        </label>

        <label data-input-image-processing-field class="grid gap-1 text-sm font-bold">
            <span>{{ __('ai_models.input_image_jpeg_quality') }}</span>
            <input
                name="settings[input_image_jpeg_quality]"
                type="text"
                inputmode="numeric"
                data-number-input
                value="{{ old('settings.input_image_jpeg_quality', $access ? data_get($access->settings ?? [], 'input_image_jpeg_quality') : $inputImageDefaults['input_image_jpeg_quality']) }}"
                class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500"
                placeholder="{{ __('ai_models.input_image_jpeg_quality_placeholder') }}"
                dir="ltr"
            >
        </label>
    </div>

    @if($settingFields->isNotEmpty())
        <div class="grid gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 md:col-span-2 md:grid-cols-2">
            <p class="text-sm font-bold text-zinc-700 md:col-span-2">{{ __('ai_models.settings') }}</p>
            @foreach($settingFields as $field)
                @php
                    $isNumericSetting = in_array($field, ['output_compression', 'timeout_seconds', 'connect_timeout_seconds'], true);
                    $selectOptions = match ($field) {
                        'quality' => ['low', 'medium', 'high'],
                        'output_format' => ['jpeg', 'png', 'webp'],
                        'response_format' => ['url', 'b64_json'],
                        'background' => ['opaque', 'transparent', 'auto'],
                        'moderation' => ['auto', 'low'],
                        default => [],
                    };
                    $settingValue = old('settings.'.$field, data_get($access?->settings ?? [], $field));
                @endphp
                @if($selectOptions !== [])
                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('ai_models.'.$field) }}</span>
                        <select
                            name="settings[{{ $field }}]"
                            class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500"
                            dir="ltr"
                        >
                            <option value="">{{ __('ai_models.setting_placeholder') }}</option>
                            @foreach($selectOptions as $option)
                                <option value="{{ $option }}" @selected($settingValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('ai_models.'.$field) }}</span>
                        <input
                            name="settings[{{ $field }}]"
                            value="{{ $settingValue }}"
                            @if($isNumericSetting) type="text" inputmode="numeric" data-number-input @endif
                            class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-teal-500"
                            placeholder="{{ __('ai_models.'.$field) }} - {{ __('ai_models.setting_placeholder') }}"
                            dir="ltr"
                            autocomplete="off"
                        >
                    </label>
                @endif
            @endforeach
        </div>
    @endif

    <label class="grid gap-1 text-sm font-bold">
        <span>{{ __('ai_models.credit_limit') }}</span>
        <input name="credit_limit" type="text" inputmode="numeric" data-number-input value="{{ old('credit_limit', $access?->credit_limit) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.unlimited_credit') }}" dir="ltr">
    </label>

    <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $access?->is_active ?? true))>
        <span>{{ __('ai_models.statuses.active') }}</span>
    </label>

    <x-admin.button type="submit" icon="sparkles" class="md:col-span-2">
        <span>{{ __('system.submit') }}</span>
    </x-admin.button>
</div>
