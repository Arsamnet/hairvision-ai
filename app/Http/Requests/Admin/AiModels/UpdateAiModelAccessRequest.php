<?php

namespace App\Http\Requests\Admin\AiModels;

use App\Services\AI\AiModelResolver;
use App\Services\AI\InputImageProcessingSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates AI model access updates without exposing stored credentials.
 *
 * @since 1.0.0
 */
class UpdateAiModelAccessRequest extends FormRequest
{
    /**
     * Determine whether the current user may submit this request.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function authorize(): bool
    {
        return ($this->user()?->can('update', $this->route('aiModelAccess')) ?? false)
            && $this->canManageSubmittedSensitiveFields();
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function rules(): array
    {
        return [
            'model_key' => ['required', 'string', Rule::in($this->modelKeys())],
            'usage_category_key' => ['required', 'string', Rule::in(array_keys(app(AiModelResolver::class)->usageCategories()))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
            'settings' => ['array'],
            'settings.'.InputImageProcessingSettings::ENABLED => ['nullable', 'boolean'],
            'settings.'.InputImageProcessingSettings::MAX_DIMENSION => [
                Rule::requiredIf($this->boolean('settings.'.InputImageProcessingSettings::ENABLED)),
                'nullable',
                'integer',
                'min:'.InputImageProcessingSettings::MIN_DIMENSION,
                'max:'.InputImageProcessingSettings::MAX_DIMENSION_LIMIT,
            ],
            'settings.'.InputImageProcessingSettings::JPEG_QUALITY => [
                Rule::requiredIf($this->boolean('settings.'.InputImageProcessingSettings::ENABLED)),
                'nullable',
                'integer',
                'min:'.InputImageProcessingSettings::MIN_JPEG_QUALITY,
                'max:'.InputImageProcessingSettings::MAX_JPEG_QUALITY,
            ],
            'settings.endpoint_url' => ['nullable', 'url', 'max:2048'],
            'settings.model' => ['nullable', 'string', 'max:100'],
            'settings.quality' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'settings.size' => ['nullable', 'string', 'regex:/^(auto|\d{1,4}x\d{1,4})$/'],
            'settings.output_format' => ['nullable', Rule::in(['jpeg', 'png', 'webp'])],
            'settings.response_format' => ['nullable', Rule::in(['url', 'b64_json'])],
            'settings.background' => ['nullable', Rule::in(['opaque', 'transparent', 'auto'])],
            'settings.moderation' => ['nullable', Rule::in(['auto', 'low'])],
            'settings.output_compression' => ['nullable', 'integer', 'min:0', 'max:100'],
            'settings.timeout_seconds' => ['nullable', 'integer', 'min:60'],
            'settings.connect_timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Execute the with validator operation.
     *
     * @param  Validator  $validator  The validator value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $access = $this->route('aiModelAccess');
            $modelKey = (string) $this->input('model_key');
            $usageCategoryKey = (string) $this->input('usage_category_key');
            $model = config("ai-models.models.$modelKey");

            if (! is_array($model)) {
                return;
            }

            if (! app(AiModelResolver::class)->modelSupportsUsageCategory($modelKey, $usageCategoryKey)) {
                $validator->errors()->add('usage_category_key', __('ai_models.errors.unsupported_usage_category'));
            }

            foreach ($model['required_credentials'] ?? [] as $field) {
                $newValue = data_get($this->input('credentials', []), $field);
                $oldValue = data_get($access?->credentials ?? [], $field);

                if ($this->boolean('is_active') && ! filled($newValue) && ! filled($oldValue)) {
                    $validator->errors()->add("credentials.$field", __('ai_models.errors.required_credential', ['field' => $field]));
                }
            }
        });
    }

    /**
     * Normalize request input before validation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        $normalized = ['is_active' => $this->boolean('is_active')];

        if ($this->has('settings')) {
            $settings = (array) $this->input('settings', []);
            $settings[InputImageProcessingSettings::ENABLED] = $this->boolean(
                'settings.'.InputImageProcessingSettings::ENABLED
            );
            $normalized['settings'] = $settings;
        }

        $this->merge($normalized);
    }

    /**
     * Return localized validation attribute names.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function attributes(): array
    {
        return [
            'settings.'.InputImageProcessingSettings::MAX_DIMENSION => __('ai_models.input_image_max_dimension'),
            'settings.'.InputImageProcessingSettings::JPEG_QUALITY => __('ai_models.input_image_jpeg_quality'),
            'settings.timeout_seconds' => __('ai_models.timeout_seconds'),
            'settings.connect_timeout_seconds' => __('ai_models.connect_timeout_seconds'),
            'settings.response_format' => __('ai_models.response_format'),
        ];
    }

    /**
     * Execute the can manage submitted sensitive fields operation.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function canManageSubmittedSensitiveFields(): bool
    {
        $tenantId = $this->attributes->get('adminTenant')?->id;
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $credentials = collect($this->input('credentials', []))->filter(fn (mixed $value): bool => filled($value));

        if ($credentials->isNotEmpty() && ! $user->hasPermission('ai_models.manage_credentials', $tenantId)) {
            return false;
        }

        if ($this->filled('credit_limit') && ! $user->hasPermission('ai_models.manage_credit', $tenantId)) {
            return false;
        }

        return true;
    }

    /**
     * Execute the model keys operation.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function modelKeys(): array
    {
        return collect(config('ai-models.models', []))
            ->keys()
            ->values()
            ->all();
    }
}
