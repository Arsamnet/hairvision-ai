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
    /**
     * Validate a Jalali date value.
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
        if (! is_scalar($value) || ! \App\Support\JalaliDate::isValidJalaliDate((string) $value)) {
            $fail(__('validation.jalali_date'));
        }
    }
}
