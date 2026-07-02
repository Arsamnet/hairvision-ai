<?php

namespace App\Http\Requests\Admin\AiModels;

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
    public function authorize(): bool
    {
        return ($this->user()?->can('update', $this->route('aiModelAccess')) ?? false)
            && $this->canManageSubmittedSensitiveFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model_key' => ['required', 'string', Rule::in(array_keys(config('ai-models.models', [])))],
            'usage_category_key' => ['required', 'string', Rule::in(array_keys(config('ai-models.usage_categories', [])))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
            'settings' => ['array'],
            'settings.stage_prompts' => ['nullable', 'array'],
            'settings.stage_prompts.*' => ['nullable', 'string', 'max:20000'],
            'prompt' => ['nullable', 'string', 'max:20000'],
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

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

            if (! in_array($usageCategoryKey, $model['supported_usage_categories'] ?? [], true)) {
                $validator->errors()->add('usage_category_key', __('ai_models.errors.unsupported_usage_category'));
            }

            foreach ($model['required_credentials'] ?? [] as $field) {
                $newValue = data_get($this->input('credentials', []), $field);
                $oldValue = data_get($access?->credentials ?? [], $field);

                if (! filled($newValue) && ! filled($oldValue)) {
                    $validator->errors()->add("credentials.$field", __('ai_models.errors.required_credential', ['field' => $field]));
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

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

        $stagePrompts = collect((array) data_get($this->input('settings', []), 'stage_prompts'))
            ->filter(fn (mixed $value): bool => filled($value));

        if (($this->filled('prompt') || $stagePrompts->isNotEmpty()) && ! $user->hasPermission('ai_models.manage_prompt', $tenantId)) {
            return false;
        }

        if ($this->filled('credit_limit') && ! $user->hasPermission('ai_models.manage_credit', $tenantId)) {
            return false;
        }

        return true;
    }
}
