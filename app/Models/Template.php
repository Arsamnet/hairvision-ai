<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\LandingAssetUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Public landing-page template option for tenant theme settings.
 *
 * @since 1.0.0
 */
class Template extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'code',
        'name',
        'description',
        'primary_color',
        'favicon_path',
        'consent_text',
        'header_html',
        'body_html',
        'footer_html',
        'back_link_title',
        'back_link_url',
        'is_active',
        'is_default',
        'revision',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'revision' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant theme rows currently assigned to this template.
     *
     * @return HasMany<TenantThemeSetting, $this>
     *
     * @since 2.0.0
     */
    public function themeSettings(): HasMany
    {
        return $this->hasMany(TenantThemeSetting::class);
    }

    /**
     * Resolve a safe favicon URL for this centrally managed template.
     *
     * @param  string  $fallback  Trusted application favicon URL.
     * @return string Safe favicon URL.
     *
     * @since 2.0.0
     */
    public function faviconHref(string $fallback): string
    {
        return LandingAssetUrl::resolve($this->favicon_path, $fallback);
    }
}
