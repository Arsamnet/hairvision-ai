<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;

/**
 * Central Jalali/Shamsi date conversion, validation, and formatting utility.
 *
 * @since 1.0.0
 */
class JalaliDate
{
    public const DATE_FORMAT = 'Y/m/d';

    public const DATE_TIME_FORMAT = 'Y/m/d H:i:s';

    public const STORAGE_LENGTH = 19;

    /**
     * Execute the to jalali date time operation.
     *
     * @param  DateTimeInterface|string|null  $value  Input value supplied to the operation.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function toJalaliDateTime(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && self::isValidJalaliDateTime($value)) {
            return self::normalizeDateTime($value);
        }

        return Jalalian::fromDateTime(self::toCarbon($value))->format(self::DATE_TIME_FORMAT);
    }

    /**
     * Execute the to jalali date operation.
     *
     * @param  DateTimeInterface|string|null  $value  Input value supplied to the operation.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function toJalaliDate(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && self::isValidJalaliDate($value)) {
            return self::normalizeDate($value);
        }

        return Jalalian::fromDateTime(self::toCarbon($value))->format(self::DATE_FORMAT);
    }

    /**
     * Execute the from jalali date time operation.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return Carbon|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function fromJalaliDateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = self::normalizeDateTime($value);

        if (! self::isValidJalaliDateTime($normalized)) {
            throw new InvalidArgumentException('Invalid Jalali datetime.');
        }

        return Jalalian::fromFormat(self::DATE_TIME_FORMAT, $normalized, self::timezone())->toCarbon();
    }

    /**
     * Execute the from jalali date operation.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return Carbon|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function fromJalaliDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = self::normalizeDate($value);

        if (! self::isValidJalaliDate($normalized)) {
            throw new InvalidArgumentException('Invalid Jalali date.');
        }

        return Jalalian::fromFormat(self::DATE_FORMAT, $normalized, self::timezone())->toCarbon();
    }

    /**
     * Normalize digits.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function normalizeDigits(?string $value): ?string
    {
        return $value === null ? null : NumberNormalizer::toLatinDigits($value);
    }

    /**
     * Normalize date time.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim((string) self::normalizeDigits($value));
        $value = str_replace(['-', '.'], '/', $value);
        $value = str_replace('T', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches) === 1) {
            return sprintf('%04d/%02d/%02d 00:00:00', $matches[1], $matches[2], $matches[3]);
        }

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2}) (\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches) === 1) {
            return sprintf(
                '%04d/%02d/%02d %02d:%02d:%02d',
                $matches[1],
                $matches[2],
                $matches[3],
                $matches[4],
                $matches[5],
                $matches[6] ?? 0,
            );
        }

        return $value;
    }

    /**
     * Normalize date.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim((string) self::normalizeDigits($value));
        $value = str_replace(['-', '.'], '/', $value);

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})/', $value, $matches) === 1) {
            return sprintf('%04d/%02d/%02d', $matches[1], $matches[2], $matches[3]);
        }

        return $value;
    }

    /**
     * Determine whether valid jalali date time.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public static function isValidJalaliDateTime(?string $value): bool
    {
        $raw = trim((string) self::normalizeDigits((string) $value));
        $raw = str_replace(['-', '.', 'T'], ['/', '/', ' '], $raw);

        if (preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}\s+\d{1,2}:\d{1,2}(:\d{1,2})?$/', $raw) !== 1) {
            return false;
        }

        $normalized = self::normalizeDateTime($value);

        if ($normalized === null || preg_match('/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}$/', $normalized) !== 1) {
            return false;
        }

        try {
            [$date, $time] = explode(' ', $normalized);
            [$hour, $minute, $second] = array_map('intval', explode(':', $time));

            if (! self::isValidJalaliDate($date) || $hour > 23 || $minute > 59 || $second > 59) {
                return false;
            }

            return Jalalian::fromFormat(self::DATE_TIME_FORMAT, $normalized, self::timezone())
                ->format(self::DATE_TIME_FORMAT) === $normalized;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Determine whether valid jalali date.
     *
     * @param  string|null  $value  Input value supplied to the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public static function isValidJalaliDate(?string $value): bool
    {
        $normalized = self::normalizeDate($value);

        if ($normalized === null || preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $normalized) !== 1) {
            return false;
        }

        try {
            [$year, $month, $day] = array_map('intval', explode('/', $normalized));

            if ($month < 1 || $month > 12 || $day < 1) {
                return false;
            }

            if ($day > (new Jalalian($year, $month, 1))->getMonthDays()) {
                return false;
            }

            return Jalalian::fromFormat(self::DATE_FORMAT, $normalized, self::timezone())
                ->format(self::DATE_FORMAT) === $normalized;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Execute the now date time string operation.
     *
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function nowDateTimeString(): string
    {
        return self::toJalaliDateTime(CarbonImmutable::now(self::timezone()));
    }

    /**
     * Execute the compare date time operation.
     *
     * @param  DateTimeInterface|string|null  $left  The left value.
     * @param  DateTimeInterface|string|null  $right  The right value.
     * @return int|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function compareDateTime(DateTimeInterface|string|null $left, DateTimeInterface|string|null $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }

        return self::toCarbon($left)->getTimestamp() <=> self::toCarbon($right)->getTimestamp();
    }

    /**
     * Execute the to carbon operation.
     *
     * @param  DateTimeInterface|string  $value  Input value supplied to the operation.
     * @return Carbon Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function toCarbon(DateTimeInterface|string $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone(self::timezone());
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->timezone(self::timezone());
        }

        $normalized = self::normalizeDateTime($value);

        if (self::isValidJalaliDateTime($normalized)) {
            return self::fromJalaliDateTime($normalized);
        }

        if (self::isValidJalaliDate($value)) {
            return self::fromJalaliDate($value)->startOfDay();
        }

        return Carbon::parse($value, self::timezone());
    }

    /**
     * Execute the timezone operation.
     *
     * @return DateTimeZone Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('app.timezone', 'Asia/Tehran'));
    }
}
