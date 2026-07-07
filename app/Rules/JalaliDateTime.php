<?php

namespace App\Rules;

use App\Support\JalaliDate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates zero-padded Jalali date-time input.
 *
 * @since 1.0.0
 */
class JalaliDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! JalaliDate::isValidJalaliDateTime((string) $value)) {
            $fail(__('validation.jalali_datetime'));
        }
    }
}
