<?php

namespace App\Services\Landing;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantThemeSetting;
use App\Support\LandingAssetUrl;

/**
 * Resolves the effective tenant landing template while preserving legacy tenant customizations.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class LandingTemplateResolver
{
    private const FALLBACK_TEMPLATE_CODE = 'classic';

    /**
     * Create the effective landing-template resolver.
     *
     * @param  LandingTemplateVariableRenderer  $variables  Safe variable renderer.
     *
     * @since 2.1.0
     */
    public function __construct(private readonly LandingTemplateVariableRenderer $variables) {}

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
     *     seo_title: string|null,
     *     seo_description: string|null,
     *     seo_image_path: string|null,
     *     seo_robots: string,
     *     tracking_head_code: string|null,
     *     tracking_body_code: string|null,
     *     header_html: string,
     *     body_html: string|null,
     *     footer_html: string,
     *     back_link_title: string|null,
     *     back_link_url: string|null,
     *     back_link_target: '_self'|'_blank',
     *     renders_back_link_in_template: bool
     * } Effective landing presentation.
     *
     * @since 2.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function resolve(Tenant $tenant): array
    {
        $theme = $this->themeFor($tenant);
        $assignedTemplate = $theme?->template;
        $defaultTemplate = $this->defaultTemplate();
        $usesTemplateContent = (bool) ($theme?->use_template_content ?? true);
        $template = $assignedTemplate?->is_active ? $assignedTemplate : $defaultTemplate;
        $defaults = $this->fallbackContent();
        $backLink = [
            'title' => $this->tenantOverrideOrValue($theme, $template, 'back_link_title', $usesTemplateContent),
            'url' => $this->tenantOverrideOrValue($theme, $template, 'back_link_url', $usesTemplateContent),
            'target' => $this->backLinkTarget($theme),
        ];
        $headerHtml = (string) $this->value($theme, $template, 'header_html', $defaults['header_html'], $usesTemplateContent);
        $bodyHtml = $this->nullableValue($theme, $template, 'body_html', $usesTemplateContent);
        $footerHtml = (string) $this->value($theme, $template, 'footer_html', $defaults['footer_html'], $usesTemplateContent);
        $rendersBackLink = $this->variables->rendersBackLink([$headerHtml, $bodyHtml, $footerHtml]);

        return [
            'template_id' => $template?->id,
            'template_code' => $template?->code ?? self::FALLBACK_TEMPLATE_CODE,
            'template_name' => $template?->name,
            'uses_template_content' => $usesTemplateContent,
            'primary_color' => (string) $this->value($theme, $template, 'primary_color', $defaults['primary_color'], $usesTemplateContent),
            'favicon_path' => $this->nullableValue($theme, $template, 'favicon_path', $usesTemplateContent),
            'consent_text' => (string) $this->value($theme, $template, 'consent_text', $defaults['consent_text'], $usesTemplateContent),
            'seo_title' => $this->nullableValue($theme, $template, 'seo_title', $usesTemplateContent),
            'seo_description' => $this->nullableValue($theme, $template, 'seo_description', $usesTemplateContent),
            'seo_image_path' => $this->nullableValue($theme, $template, 'seo_image_path', $usesTemplateContent),
            'seo_robots' => $this->seoRobots($template),
            'tracking_head_code' => $this->nullableValue($theme, $template, 'tracking_head_code', $usesTemplateContent),
            'tracking_body_code' => $this->nullableValue($theme, $template, 'tracking_body_code', $usesTemplateContent),
            'header_html' => $this->variables->render($headerHtml, $backLink),
            'body_html' => $bodyHtml === null ? null : $this->variables->render($bodyHtml, $backLink),
            'footer_html' => $this->variables->render($footerHtml, $backLink),
            'back_link_title' => $backLink['title'],
            'back_link_url' => $backLink['url'],
            'back_link_target' => $backLink['target'],
            'renders_back_link_in_template' => $rendersBackLink,
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

    /**
     * Prefer a clinic-owned link override before falling back to template content.
     *
     * @param  TenantThemeSetting|null  $theme  Tenant-specific settings.
     * @param  Template|null  $template  Effective active template.
     * @param  string  $field  Link presentation field.
     * @param  bool  $usesTemplateContent  Whether central template content is authoritative for other fields.
     * @return string|null Resolved optional link value.
     *
     * @since 2.1.0
     */
    private function tenantOverrideOrValue(
        ?TenantThemeSetting $theme,
        ?Template $template,
        string $field,
        bool $usesTemplateContent,
    ): ?string {
        if (filled($theme?->{$field})) {
            return (string) $theme?->{$field};
        }

        return $this->nullableValue($theme, $template, $field, $usesTemplateContent);
    }

    /**
     * Resolve the allowlisted browser target for the clinic back link.
     *
     * @param  TenantThemeSetting|null  $theme  Tenant-specific settings.
     * @return '_self'|'_blank' Safe HTML target value.
     *
     * @since 2.1.0
     */
    private function backLinkTarget(?TenantThemeSetting $theme): string
    {
        $target = (string) ($theme?->back_link_target ?? '_self');

        return in_array($target, ['_self', '_blank'], true) ? $target : '_self';
    }

    /**
     * Resolve the allowlisted crawler directive for the effective template.
     *
     * @param  Template|null  $template  Effective active template.
     * @return string Safe robots directive.
     *
     * @since 2.1.0
     */
    private function seoRobots(?Template $template): string
    {
        $robots = (string) ($template?->seo_robots ?: 'index,follow');

        return in_array($robots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)
            ? $robots
            : 'index,follow';
    }

    /**
     * Return a minimal emergency presentation when no seeded template is available.
     *
     * @return array{
     *     primary_color: string,
     *     consent_text: string,
     *     header_html: string,
     *     footer_html: string
     * } Safe code-owned presentation fallback.
     *
     * @since 2.1.0
     */
    private function fallbackContent(): array
    {
        return [
            'primary_color' => '#0f766e',
            'consent_text' => 'با پردازش تصویر برای این سرویس موافقم.',
            'header_html' => '<div class="mx-auto w-full max-w-6xl px-4 py-5"><p class="text-xl font-bold text-zinc-950">HairVision</p></div>',
            'footer_html' => '<div class="mx-auto w-full max-w-6xl px-4 py-5 text-sm text-zinc-500"><p>پیش‌نمایش هوشمند برای شروع مشاوره با کلینیک.</p></div>',
        ];
    }
}
