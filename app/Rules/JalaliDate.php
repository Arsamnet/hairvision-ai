<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates zero-padded Jalali date input.
 *
 * @since 1.0.0
 */
class JalaliDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! \App\Support\JalaliDate::isValidJalaliDate((string) $value)) {
            $fail(__('validation.jalali_date'));
        }
    }
}
