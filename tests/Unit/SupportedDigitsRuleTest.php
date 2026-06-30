<?php

namespace Tests\Unit;

use App\Rules\SupportedDigits;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SupportedDigitsRuleTest extends TestCase
{
    public function test_it_accepts_persian_arabic_and_latin_digits_only(): void
    {
        $this->assertFalse(Validator::make(['code' => '۱۲۳٤٥6'], [
            'code' => [new SupportedDigits('invalid')],
        ])->fails());

        $this->assertTrue(Validator::make(['code' => '۱۲۳-456'], [
            'code' => [new SupportedDigits('invalid')],
        ])->fails());
    }
}
