<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\LandingAssetUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-controlled public landing-page theme settings.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class TenantThemeSetting extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'use_template_content',
        'favicon_path',
        'logo_path',
        'primary_color',
        'hero_title',
        'hero_subtitle',
        'cta_text',
        'background_path',
        'consent_text',
        'footer_text',
        'header_html',
        'body_html',
        'footer_html',
        'back_link_title',
        'back_link_url',
        'back_link_target',
        'settings_json',
    ];

    protected $casts = [
        'use_template_content' => 'boolean',
        'settings_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns these theme settings.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 1.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Resolve a safe favicon URL, falling back when tenant input is invalid.
     *
     * @param  string  $fallback  Trusted application favicon URL.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function faviconHref(string $fallback): string
    {
        return LandingAssetUrl::resolve($this->favicon_path, $fallback);
    }

    /**
     * Template selected for this tenant theme.
     *
     * @return BelongsTo<Template, $this>
     *
     * @since 1.0.0
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
