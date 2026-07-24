<?php

namespace App\Support;

/**
 * Normalizes Persian, Arabic, and Latin digit strings for validation/storage.
 *
 * @since 1.0.0
 */
class NumberNormalizer
{
    private const TO_LATIN_DIGITS = [
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ];

    private const TO_PERSIAN_DIGITS = [
        '0' => '۰',
        '1' => '۱',
        '2' => '۲',
        '3' => '۳',
        '4' => '۴',
        '5' => '۵',
        '6' => '۶',
        '7' => '۷',
        '8' => '۸',
        '9' => '۹',
    ];

    /**
     * Convert supported Persian and Arabic numerals to Latin digits.
     *
     * @param  string  $value  Input value supplied to the operation.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function toLatinDigits(string $value): string
    {
        return strtr($value, self::TO_LATIN_DIGITS);
    }

    /**
     * Normalize a complete numeric string for validation and storage.
     *
     * Persian, Arabic, and Latin digits are accepted. Valid comma-grouped
     * integers and Arabic decimal separators are converted to an unformatted
     * Latin representation. Non-numeric text is returned unchanged.
     *
     * @param  string  $value  Input value supplied to the operation.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function normalizeNumericString(string $value): string
    {
        $trimmed = trim($value);

        if (preg_match(
            '/^[+-]?(?:(?:[0-9۰-۹٠-٩]{1,3}(?:[,،٬][0-9۰-۹٠-٩]{3})+)|[0-9۰-۹٠-٩]+)(?:[.٫][0-9۰-۹٠-٩]+)?$/u',
            $trimmed
        ) !== 1) {
            return $value;
        }

        return strtr(self::toLatinDigits($trimmed), [
            ',' => '',
            '،' => '',
            '٬' => '',
            '٫' => '.',
        ]);
    }

    /**
     * Convert Latin digits to Persian numerals for user-facing debug text.
     *
     * @param  string  $value  Input value supplied to the operation.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function toPersianDigits(string $value): string
    {
        return strtr($value, self::TO_PERSIAN_DIGITS);
    }

    /**
     * Strip non-digit characters after digit-script normalization.
     *
     * @param  string  $value  Input value supplied to the operation.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function digitsOnly(string $value): string
    {
        return preg_replace('/[^0-9]/', '', self::toLatinDigits($value));
    }

    /**
     * Determine whether all characters are Persian, Arabic, or Latin digits.
     *
     * @param  string  $value  Input value supplied to the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public static function hasOnlySupportedDigits(string $value): bool
    {
        return preg_match('/^[0-9۰-۹٠-٩]+$/u', $value) === 1;
    }
}
