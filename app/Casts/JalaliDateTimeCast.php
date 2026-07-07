<?php

namespace App\Casts;

use App\Support\JalaliDate;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores business date-times as normalized zero-padded Jalali strings.
 *
 * @implements CastsAttributes<string|null, mixed>
 *
 * @since 1.0.0
 */
class JalaliDateTimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return JalaliDate::normalizeDateTime((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = JalaliDate::toJalaliDateTime($value);

        if (! JalaliDate::isValidJalaliDateTime($normalized)) {
            throw new InvalidArgumentException("The {$key} field must be a valid Jalali datetime.");
        }

        return $normalized;
    }
}
