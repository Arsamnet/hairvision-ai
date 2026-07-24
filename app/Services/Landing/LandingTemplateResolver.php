<?php

namespace App\Services\Landing;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantThemeSetting;
use App\Support\LandingAssetUrl;
use App\Support\LandingTemplateDefaults;

/**
 * Resolves the effective tenant landing template while preserving legacy tenant customizations.
 *
 * @since 2.0.0
 */
class LandingTemplateResolver
{
    /**
     * Resolve the complete landing presentation for a tenant.
     *
     * @param  Tenant  $tenant  Tenant resolved from the request host.
     * @return array{
     *     template_id: int|null,
     *     template_code: string,
     *     template_name: string|null,
     *     uses_template_content: bool,
     *     primary_color: string,
     *     favicon_path: string|null,
     *     consent_text: string,
     *     header_html: string,
     *     body_html: string|null,
     *     footer_html: string,
     *     back_link_title: string|null,
     *     back_link_url: string|null
     * } Effective landing presentation.
     *
     * @since 2.0.0
     */
    public function resolve(Tenant $tenant): array
    {
        $theme = $this->themeFor($tenant);
        $assignedTemplate = $theme?->template;
        $defaultTemplate = $this->defaultTemplate();
        $usesTemplateContent = (bool) ($theme?->use_template_content ?? true);
        $template = $assignedTemplate?->is_active ? $assignedTemplate : $defaultTemplate;
        $defaults = LandingTemplateDefaults::content();

        return [
            'template_id' => $template?->id,
            'template_code' => $template?->code ?? LandingTemplateDefaults::CODE,
            'template_name' => $template?->name,
            'uses_template_content' => $usesTemplateContent,
            'primary_color' => (string) $this->value($theme, $template, 'primary_color', $defaults['primary_color'], $usesTemplateContent),
            'favicon_path' => $this->nullableValue($theme, $template, 'favicon_path', $usesTemplateContent),
            'consent_text' => (string) $this->value($theme, $template, 'consent_text', $defaults['consent_text'], $usesTemplateContent),
            'header_html' => (string) $this->value($theme, $template, 'header_html', $defaults['header_html'], $usesTemplateContent),
            'body_html' => $this->nullableValue($theme, $template, 'body_html', $usesTemplateContent),
            'footer_html' => (string) $this->value($theme, $template, 'footer_html', $defaults['footer_html'], $usesTemplateContent),
            'back_link_title' => $this->nullableValue($theme, $template, 'back_link_title', $usesTemplateContent),
            'back_link_url' => $this->nullableValue($theme, $template, 'back_link_url', $usesTemplateContent),
        ];
    }

    /**
     * Resolve the safe favicon URL for an effective landing presentation.
     *
     * @param  array<string, mixed>  $presentation  Resolved landing presentation.
     * @param  string  $fallback  Trusted application favicon URL.
     * @return string Safe favicon URL.
     *
     * @since 2.0.0
     */
    public function faviconHref(array $presentation, string $fallback): string
    {
        return LandingAssetUrl::resolve(
            is_string($presentation['favicon_path'] ?? null) ? $presentation['favicon_path'] : null,
            $fallback
        );
    }

    /**
     * Return the active default landing template.
     *
     * @return Template|null Active default template, when configured.
     *
     * @since 2.0.0
     */
    public function defaultTemplate(): ?Template
    {
        return Template::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Load the single tenant theme row and its assigned template.
     *
     * @param  Tenant  $tenant  Tenant resolved from the request host.
     * @return TenantThemeSetting|null Tenant landing settings.
     *
     * @since 2.0.0
     */
    private function themeFor(Tenant $tenant): ?TenantThemeSetting
    {
        $theme = $tenant->relationLoaded('theme')
            ? $tenant->theme
            : $tenant->theme()->with('template')->first();

        $theme?->loadMissing('template');

        return $theme;
    }

    /**
     * Resolve a required presentation value from template or legacy tenant settings.
     *
     * @param  TenantThemeSetting|null  $theme  Tenant-specific settings.
     * @param  Template|null  $template  Effective active template.
     * @param  string  $field  Presentation field.
     * @param  mixed  $fallback  Built-in fallback.
     * @param  bool  $usesTemplateContent  Whether central template content is authoritative.
     * @return mixed Resolved presentation value.
     *
     * @since 2.0.0
     */
    private function value(
        ?TenantThemeSetting $theme,
        ?Template $template,
        string $field,
        mixed $fallback,
        bool $usesTemplateContent,
    ): mixed {
        if (! $usesTemplateContent && filled($theme?->{$field})) {
            return $theme?->{$field};
        }

        return filled($template?->{$field}) ? $template?->{$field} : $fallback;
    }

    /**
     * Resolve an optional presentation value.
     *
     * @param  TenantThemeSetting|null  $theme  Tenant-specific settings.
     * @param  Template|null  $template  Effective active template.
     * @param  string  $field  Presentation field.
     * @param  bool  $usesTemplateContent  Whether central template content is authoritative.
     * @return string|null Resolved optional string.
     *
     * @since 2.0.0
     */
    private function nullableValue(
        ?TenantThemeSetting $theme,
        ?Template $template,
        string $field,
        bool $usesTemplateContent,
    ): ?string {
        $value = $this->value($theme, $template, $field, null, $usesTemplateContent);

        return filled($value) ? (string) $value : null;
    }
}
