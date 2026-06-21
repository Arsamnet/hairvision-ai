<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantThemeSetting extends Model
{
    use HasFactory;

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

    protected static function booted(): void
    {
        static::creating(function (TenantThemeSetting $setting) {
            if (empty($setting->uuid)) {
                $setting->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

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

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
