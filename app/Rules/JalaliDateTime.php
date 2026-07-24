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
    /**
     * Validate a Jalali date-time value.
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
        if (! is_scalar($value) || ! JalaliDate::isValidJalaliDateTime((string) $value)) {
            $fail(__('validation.jalali_datetime'));
        }
    }
}
