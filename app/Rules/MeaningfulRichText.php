<?php

namespace App\Rules;

use App\Support\Html\SimpleRichTextSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Requires rich text to contain visible allowlisted content after sanitization.
 *
 * @since 2.0.1
 */
final class MeaningfulRichText implements ValidationRule
{
    /**
     * Create the rich-text validation rule.
     *
     * @param  SimpleRichTextSanitizer  $sanitizer  Shared HTML sanitizer.
     *
     * @since 2.0.1
     */
    public function __construct(private readonly SimpleRichTextSanitizer $sanitizer) {}

    /**
     * Validate that sanitized rich text still has visible text.
     *
     * @param  string  $attribute  Input attribute name.
     * @param  mixed  $value  Submitted value.
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  Validation failure callback.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->sanitizer->plainText($this->sanitizer->sanitize((string) $value)) === '') {
            $fail('guide.errors.empty_rich_text')->translate();
        }
    }
}
