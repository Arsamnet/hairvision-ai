<?php

namespace App\Rules;

use App\Support\NumberNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates values that may contain only Persian, Arabic, or Latin digits.
 *
 * @since 1.0.0
 */
class SupportedDigits implements ValidationRule
{
    public function __construct(private readonly ?string $message = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! NumberNormalizer::hasOnlySupportedDigits((string) $value)) {
            $fail($this->message ?? __('validation.supported_digits'));
        }
    }
}
