<?php

namespace Tests\Unit;

use App\Rules\JalaliDateTime;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class JalaliDateTest extends TestCase
{
    public function test_it_converts_gregorian_carbon_to_jalali_datetime(): void
    {
        $value = Carbon::create(2026, 4, 20, 23, 59, 59, config('app.timezone'));

        $this->assertSame('1405/01/31 23:59:59', JalaliDate::toJalaliDateTime($value));
        $this->assertSame('1405/01/31', JalaliDate::toJalaliDate($value));
    }

    public function test_it_converts_jalali_datetime_to_gregorian_carbon(): void
    {
        $this->assertSame(
            '2026-04-20 23:59:59',
            JalaliDate::fromJalaliDateTime('1405/01/31 23:59:59')?->toDateTimeString()
        );
    }

    public function test_it_normalizes_persian_and_arabic_digits(): void
    {
        $this->assertSame('1405/01/31 23:59:59', JalaliDate::normalizeDateTime('۱۴۰۵/۰۱/۳۱ ۲۳:۵۹:۵۹'));
        $this->assertSame('1405/01/31 23:59:59', JalaliDate::normalizeDateTime('١٤٠٥/٠١/٣١ ٢٣:٥٩:٥٩'));
    }

    public function test_it_rejects_invalid_or_malformed_jalali_dates(): void
    {
        $this->assertFalse(JalaliDate::isValidJalaliDate('1405/13/40'));
        $this->assertFalse(JalaliDate::isValidJalaliDateTime('1405/01/31'));
        $this->assertTrue(JalaliDate::isValidJalaliDate('1405/01/31'));
        $this->assertTrue(JalaliDate::isValidJalaliDateTime('1405/01/31 23:59:59'));
    }

    public function test_it_handles_nulls_and_validates_rule_input(): void
    {
        $this->assertNull(JalaliDate::toJalaliDateTime(null));

        $this->assertFalse(Validator::make(['date' => '۱۴۰۵/۰۱/۳۱ ۲۳:۵۹:۵۹'], [
            'date' => [new JalaliDateTime],
        ])->fails());

        $this->assertTrue(Validator::make(['date' => '1405/13/40 23:59:59'], [
            'date' => [new JalaliDateTime],
        ])->fails());
    }
}
