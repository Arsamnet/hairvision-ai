<?php

namespace Tests\Unit;

use App\Support\NumberNormalizer;
use PHPUnit\Framework\TestCase;

class NumberNormalizerTest extends TestCase
{
    public function test_it_converts_persian_arabic_and_latin_digits_to_latin(): void
    {
        $this->assertSame('09123456789', NumberNormalizer::toLatinDigits('۰۹۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('09123456789', NumberNormalizer::toLatinDigits('٠٩١٢٣٤٥٦٧٨٩'));
        $this->assertSame('09123456789', NumberNormalizer::toLatinDigits('09123456789'));
    }

    public function test_it_can_strip_separators_after_digit_normalization(): void
    {
        $this->assertSame('09123456789', NumberNormalizer::digitsOnly('۰۹۱۲-۳۴۵-۶۷۸۹'));
    }

    public function test_it_detects_supported_digit_only_strings(): void
    {
        $this->assertTrue(NumberNormalizer::hasOnlySupportedDigits('۱۲۳٤٥6'));
        $this->assertFalse(NumberNormalizer::hasOnlySupportedDigits('۱۲۳-456'));
    }

    public function test_it_converts_latin_digits_to_persian_for_display(): void
    {
        $this->assertSame('۱۲۳۴۵۶', NumberNormalizer::toPersianDigits('123456'));
    }
}
