<?php

namespace App\Services\AI;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Safe schema registry for configured AI model types.
 *
 * @since 2.0.0
 */
class AiModelTypeRegistry
{
    /**
     * Execute the all operation.
     *
     * @return array<string, array<string, mixed>>
     *
     * @since 2.0.0
     */
    public function all(): array
    {
        return (array) config('ai-models.types', []);
    }

    /**
     * Retrieve get.
     *
     * @param  string  $type  The type value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public function get(string $type): array
    {
        $definition = $this->all()[$type] ?? null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException('Unknown AI model type.');
        }

        return $definition;
    }

    /**
     * Return schema data safe for API/dashboard display.
     *
     * @param  string  $type  The type value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public function publicDefinition(string $type): array
    {
        $definition = $this->get($type);

        return [
            'key' => $type,
            'label' => $definition['label'] ?? $type,
            'credentials' => $this->publicFields((array) ($definition['credentials'] ?? [])),
            'settings' => $this->publicFields((array) ($definition['settings'] ?? [])),
            'output_fields' => $this->publicFields((array) ($definition['output_fields'] ?? [])),
            'defaults' => $this->publicDefaults((array) ($definition['defaults'] ?? []), [
                ...(array) ($definition['credentials'] ?? []),
            ]),
            'testable' => (bool) ($definition['testable'] ?? false),
        ];
    }

    /**
     * Execute the public definitions operation.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 2.0.0
     */
    public function publicDefinitions(): array
    {
        return collect(array_keys($this->all()))
            ->map(fn (string $type): array => $this->publicDefinition($type))
            ->values()
            ->all();
    }

    /**
     * Validate and whitelist model-level settings.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $type  The type value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public function validateSettings(string $type, array $payload): array
    {
        return $this->validateFieldPayload($type, 'settings', $payload);
    }

    /**
     * Validate and whitelist model credentials.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $type  The type value.
     * @param  bool  $partial  The partial value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public function validateCredentials(string $type, array $payload, bool $partial = false): array
    {
        return $this->validateFieldPayload($type, 'credentials', $payload, $partial);
    }

    /**
     * Validate and whitelist one output config payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $type  The type value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public function validateOutputConfig(string $type, array $payload): array
    {
        return $this->validateFieldPayload($type, 'output_fields', $payload);
    }

    /**
     * Validate field payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $type  The type value.
     * @param  string  $section  The section value.
     * @param  bool  $partial  The partial value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    private function validateFieldPayload(string $type, string $section, array $payload, bool $partial = false): array
    {
        $definition = $this->get($type);
        $fields = (array) ($definition[$section] ?? []);
        $keys = collect($fields)->pluck('key')->filter()->map(fn (mixed $key): string => (string) $key)->all();

        $unknown = array_diff(array_keys($payload), $keys);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                $section => __('validation.prohibited', ['attribute' => implode(', ', $unknown)]),
            ]);
        }

        $rules = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null)) {
                continue;
            }

            $fieldRules = (array) ($field['rules'] ?? []);

            if ($partial && ! array_key_exists($field['key'], $payload)) {
                $fieldRules = array_values(array_filter($fieldRules, fn (mixed $rule): bool => $rule !== 'required'));
                array_unshift($fieldRules, 'sometimes');
            }

            $rules[$field['key']] = $fieldRules;
        }

        Validator::make($payload, $rules)->validate();

        return Arr::only($payload, $keys);
    }

    /**
     * Execute the public fields operation.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     *
     * @since 2.0.0
     */
    private function publicFields(array $fields): array
    {
        return collect($fields)
            ->map(function (array $field): array {
                return Arr::only($field, [
                    'key',
                    'label',
                    'type',
                    'required',
                    'options',
                    'default',
                    'secret',
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Execute the public defaults operation.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<int, array<string, mixed>>  $secretFields
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    private function publicDefaults(array $defaults, array $secretFields): array
    {
        $secretKeys = collect($secretFields)
            ->filter(fn (array $field): bool => (bool) ($field['secret'] ?? false))
            ->pluck('key')
            ->all();

        return Arr::except($defaults, $secretKeys);
    }
}
