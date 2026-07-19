<?php

namespace App\Http\Requests\Admin\AiModels;

use App\Models\AiModelAccess;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates AI model access creation.
 *
 * @since 1.0.0
 */
class StoreAiModelAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('create', AiModelAccess::class) ?? false)
            && $this->canManageSubmittedSensitiveFields();
    }

    /**
     * @return array<string, mixed>
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
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'return_to_index' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
                if ($this->boolean('is_active') && ! filled(data_get($this->input('credentials', []), $field))) {
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

        if ($this->filled('credit_limit') && ! $user->hasPermission('ai_models.manage_credit', $tenantId)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function modelKeys(): array
    {
        return collect(config('ai-models.models', []))
            ->keys()
            ->values()
            ->all();
    }
}
