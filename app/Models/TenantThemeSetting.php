<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-controlled public landing-page theme settings.
 *
 * @since 1.0.0
 */
class TenantThemeSetting extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'template_id',
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
        'settings_json',
    ];

    protected $casts = [
        'settings_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns these theme settings.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Resolve a safe favicon URL, falling back when tenant input is invalid.
     *
     * @param  string  $fallback  Trusted application favicon URL.
     */
    public function faviconHref(string $fallback): string
    {
        $faviconPath = trim((string) $this->favicon_path);

        if ($faviconPath === '') {
            return $fallback;
        }

        if (str_starts_with($faviconPath, '//')) {
            return $fallback;
        }

        if (str_starts_with($faviconPath, '/')) {
            $path = parse_url($faviconPath, PHP_URL_PATH);

            if (! is_string($path) || ! is_file(public_path(ltrim($path, '/')))) {
                return $fallback;
            }

            return $faviconPath;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $assetHost = parse_url($faviconPath, PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '' && $assetHost === $appHost) {
            return $faviconPath;
        }

        return $fallback;
    }

    /**
     * Template selected for this tenant theme.
     *
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
