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
    /**
     * Create a new supported digits instance.
     *
     * @param  string|null  $message  The message value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly ?string $message = null) {}

    /**
     * Validate a value containing supported digit characters.
     *
     * @param  string  $attribute  The attribute value.
     * @param  mixed  $value  Input value supplied to the operation.
     * @param  Closure  $fail  The fail value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! NumberNormalizer::hasOnlySupportedDigits((string) $value)) {
            $fail($this->message ?? __('validation.supported_digits'));
        }
    }
}
