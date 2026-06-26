<?php

namespace App\Support;

class AdminDashboardFormat
{
    public static function digits(string|int|float|null $value): string
    {
        return JalaliDate::persianDigits($value);
    }

    public static function number(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::digits(number_format((float) $value));
    }

    public static function phone(?string $value): string
    {
        return filled($value) ? self::digits($value) : '—';
    }

    public static function normalizeDigits(string $value): string
    {
        return JalaliDate::normalizeDigits($value);
    }
}
