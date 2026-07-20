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

    public function test_it_normalizes_grouped_numbers_from_all_supported_digit_scripts(): void
    {
        $this->assertSame('4500000', NumberNormalizer::normalizeNumericString('۴٬۵۰۰٬۰۰۰'));
        $this->assertSame('4500000', NumberNormalizer::normalizeNumericString('٤,٥٠٠,٠٠٠'));
        $this->assertSame('4500000', NumberNormalizer::normalizeNumericString('4,500,000'));
        $this->assertSame('123.45', NumberNormalizer::normalizeNumericString('۱۲۳٫۴۵'));
    }

    public function test_it_does_not_mutate_non_numeric_or_invalidly_grouped_text(): void
    {
        $this->assertSame('پلن ۱۲۳', NumberNormalizer::normalizeNumericString('پلن ۱۲۳'));
        $this->assertSame('۱۲,۳۴', NumberNormalizer::normalizeNumericString('۱۲,۳۴'));
    }
}
