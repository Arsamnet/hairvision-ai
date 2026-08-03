<?php

namespace App\Support\Tenancy;

use App\Models\TenantDomain;

/**
 * Classifies incoming hosts before tenant-scoped public flows run.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class HostClassifier
{
    /**
     * Normalize an HTTP host for stable comparisons.
     *
     * @param  string  $host  The host value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function normalize(string $host): string
    {
        return strtolower($host);
    }

    /**
     * Determine whether the host is the configured primary HairVision domain.
     *
     * @param  string  $host  The host value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public static function isPrimaryHost(string $host): bool
    {
        $primaryDomain = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($primaryDomain)
            && $primaryDomain !== ''
            && self::normalize($host) === self::normalize($primaryDomain);
    }

    /**
     * Determine whether a domain row represents the platform surface.
     *
     * @param  TenantDomain|null  $domain  The domain value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public static function isPlatformDomain(?TenantDomain $domain): bool
    {
        return $domain instanceof TenantDomain
            && in_array($domain->type, ['platform', 'platform_subdomain'], true);
    }

    /**
     * Determine whether a host/domain pair should be treated as platform-only.
     *
     * @param  string  $host  The host value.
     * @param  TenantDomain|null  $domain  The domain value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public static function isPlatformHost(string $host, ?TenantDomain $domain = null): bool
    {
        return self::isPrimaryHost($host) || self::isPlatformDomain($domain);
    }

    /**
     * Domain types that may serve the public clinic visitor API.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public static function visitorDomainTypes(): array
    {
        return ['tenant_subdomain', 'custom_domain', 'primary_domain'];
    }
}
