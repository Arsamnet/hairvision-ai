<?php

namespace App\Support;

/**
 * Defines the allowlisted variable contract available to managed landing templates.
 *
 * @since 2.1.0
 */
final class LandingTemplateVariables
{
    /** Clinic-specific back-link text token. */
    public const BACK_LINK_TITLE = '{{ clinic.back_link.title }}';

    /** Clinic-specific back-link URL token. */
    public const BACK_LINK_URL = '{{ clinic.back_link.url }}';

    /** Clinic-specific allowlisted browser-target token. */
    public const BACK_LINK_TARGET = '{{ clinic.back_link.target }}';

    /**
     * Return every supported template token.
     *
     * @return array<int, string> Canonical variable tokens.
     *
     * @since 2.1.0
     */
    public static function tokens(): array
    {
        return [
            self::BACK_LINK_TITLE,
            self::BACK_LINK_URL,
            self::BACK_LINK_TARGET,
        ];
    }

    /**
     * Return variable metadata for the system template editor.
     *
     * @return array<int, array{token: string, label: string, usage: string}> Variable definitions.
     *
     * @since 2.1.0
     */
    public static function definitions(): array
    {
        return [
            [
                'token' => self::BACK_LINK_TITLE,
                'label' => __('landing_templates.variables.back_link_title'),
                'usage' => __('landing_templates.variable_usage.back_link_title'),
            ],
            [
                'token' => self::BACK_LINK_URL,
                'label' => __('landing_templates.variables.back_link_url'),
                'usage' => __('landing_templates.variable_usage.back_link_url'),
            ],
            [
                'token' => self::BACK_LINK_TARGET,
                'label' => __('landing_templates.variables.back_link_target'),
                'usage' => __('landing_templates.variable_usage.back_link_target'),
            ],
        ];
    }

    /**
     * Return the recommended secure anchor markup for template authors.
     *
     * @return string Copyable template fragment.
     *
     * @since 2.1.0
     */
    public static function backLinkExample(): string
    {
        return sprintf(
            '<a href="%s" target="%s" rel="noopener noreferrer" class="hv-button hv-button-secondary hv-button-compact inline-flex">%s</a>',
            self::BACK_LINK_URL,
            self::BACK_LINK_TARGET,
            self::BACK_LINK_TITLE,
        );
    }

    /**
     * Return tokens used across a collection of HTML fragments.
     *
     * @param  array<int, string|null>  $fragments  Submitted landing fragments.
     * @return array<int, string> Used canonical tokens.
     *
     * @since 2.1.0
     */
    public static function usedTokens(array $fragments): array
    {
        $html = implode("\n", array_map(static fn (?string $fragment): string => (string) $fragment, $fragments));

        return array_values(array_filter(
            self::tokens(),
            static fn (string $token): bool => str_contains($html, $token)
        ));
    }

    /**
     * Determine whether all back-link tokens are present together.
     *
     * @param  array<int, string|null>  $fragments  Submitted landing fragments.
     * @return bool Whether the complete variable set is used.
     *
     * @since 2.1.0
     */
    public static function usesCompleteBackLinkSet(array $fragments): bool
    {
        return count(self::usedTokens($fragments)) === count(self::tokens());
    }
}
