<?php

namespace App\Services\Landing;

use App\Support\LandingTemplateVariables;

/**
 * Injects escaped tenant values into allowlisted managed-template variables.
 *
 * Values are escaped before replacement and template validation restricts the
 * tokens to their documented HTML contexts.
 *
 * @since 2.1.0
 */
class LandingTemplateVariableRenderer
{
    /**
     * Render one HTML fragment with escaped back-link values.
     *
     * @param  string  $fragment  Trusted system-managed HTML fragment.
     * @param  array{title: string|null, url: string|null, target: '_self'|'_blank'}  $backLink  Tenant link values.
     * @return string Rendered HTML fragment.
     *
     * @since 2.1.0
     */
    public function render(string $fragment, array $backLink): string
    {
        return strtr($fragment, [
            LandingTemplateVariables::BACK_LINK_TITLE => $this->escape($backLink['title']),
            LandingTemplateVariables::BACK_LINK_URL => $this->escape($backLink['url']),
            LandingTemplateVariables::BACK_LINK_TARGET => $backLink['target'],
        ]);
    }

    /**
     * Determine whether the complete back-link variable set is present.
     *
     * @param  array<int, string|null>  $fragments  Effective landing fragments.
     * @return bool Whether the template owns back-link placement.
     *
     * @since 2.1.0
     */
    public function rendersBackLink(array $fragments): bool
    {
        return LandingTemplateVariables::usesCompleteBackLinkSet($fragments);
    }

    /**
     * Escape one tenant-supplied value for its validated HTML context.
     *
     * @param  string|null  $value  Tenant setting value.
     * @return string HTML-escaped value.
     *
     * @since 2.1.0
     */
    private function escape(?string $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8',
            true
        );
    }
}
