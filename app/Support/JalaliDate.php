<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

class JalaliDate
{
    /**
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public static function date(?DateTimeInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );

        return self::persianDigits(sprintf('%04d/%02d/%02d', $year, $month, $day));
    }

    public static function dateTime(?DateTimeInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        return self::date($date).' ساعت '.self::persianDigits($date->format('H:i'));
    }

    public static function readable(?DateTimeInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );

        return self::persianDigits($day).' '.self::MONTHS[$month].' '.self::persianDigits($year)
            .'، ساعت '.self::persianDigits($date->format('H:i'));
    }

    public static function toGregorianDate(?string $value): ?string
    {
        $value = trim(self::normalizeDigits((string) $value));

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid date format.');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($year >= 1700) {
            return Carbon::createFromDate($year, $month, $day)->toDateString();
        }

        [$gregorianYear, $gregorianMonth, $gregorianDay] = self::jalaliToGregorian($year, $month, $day);

        return Carbon::createFromDate($gregorianYear, $gregorianMonth, $gregorianDay)->toDateString();
    }

    public static function inputValue(?string $value): string
    {
        try {
            $gregorian = self::toGregorianDate($value);
        } catch (InvalidArgumentException) {
            return '';
        }

        return $gregorian ? self::date(Carbon::parse($gregorian)) : '';
    }

    public static function persianDigits(string|int|float|null $value): string
    {
        return strtr((string) $value, [
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
        ]);
    }

    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
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
        ]);
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gregorianMonthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $gregorianMonthDays[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        if ($jy > 979) {
            $gy = 1600;
            $jy -= 979;
        } else {
            $gy = 621;
        }

        $days = (365 * $jy)
            + (intdiv($jy, 33) * 8)
            + intdiv((($jy % 33) + 3), 4)
            + 78
            + $jd
            + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

        $gy += 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = (($gy % 4 === 0) && ($gy % 100 !== 0)) || ($gy % 400 === 0);
        $gregorianMonthDays = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        for ($gm = 1; $gm <= 12 && $gd > $gregorianMonthDays[$gm]; $gm++) {
            $gd -= $gregorianMonthDays[$gm];
        }

        return [$gy, $gm, $gd];
    }
}
