<?php

namespace App\Support;

/**
 * Provides the localized default clinic dashboard guide content.
 *
 * @since 2.0.1
 */
final class AdminGuideDefaults
{
    /**
     * Return the structured default guide displayed before any system edit.
     *
     * @return array{
     *     title: string,
     *     introduction: string,
     *     sections: array<int, array{
     *         title: string,
     *         audience: string,
     *         description: string,
     *         steps: array<int, array{title: string, body: string}>
     *     }>,
     *     faqs: array<int, array{question: string, answer: string}>
     * }
     *
     * @since 2.0.1
     */
    public static function content(): array
    {
        /** @var array{
         *     title: string,
         *     introduction: string,
         *     sections: array<int, array{
         *         title: string,
         *         audience: string,
         *         description: string,
         *         steps: array<int, array{title: string, body: string}>
         *     }>,
         *     faqs: array<int, array{question: string, answer: string}>
         * } $content
         */
        $content = __('guide.defaults');

        return $content;
    }
}
