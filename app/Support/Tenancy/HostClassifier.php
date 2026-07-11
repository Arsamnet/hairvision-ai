<?php

namespace App\Support\Tenancy;

use App\Models\TenantDomain;

/**
 * Classifies incoming hosts before tenant-scoped public flows run.
 *
 * @since 1.0.0
 */
class HostClassifier
{
    /**
     * Normalize an HTTP host for stable comparisons.
     */
    public static function normalize(string $host): string
    {
        return strtolower($host);
    }

    /**
     * Determine whether the host is the configured primary HairVision domain.
     */
    public static function isPrimaryHost(string $host): bool
    {
        $primaryDomain = strtolower((string) config('hairvision.primary_domain'));

        return $primaryDomain !== '' && self::normalize($host) === $primaryDomain;
    }

    /**
     * Determine whether a domain row represents the platform surface.
     */
    public static function isPlatformDomain(?TenantDomain $domain): bool
    {
        return $domain instanceof TenantDomain
            && in_array($domain->type, ['platform', 'platform_subdomain'], true);
    }

    /**
     * Determine whether a host/domain pair should be treated as platform-only.
     */
    public static function isPlatformHost(string $host, ?TenantDomain $domain = null): bool
    {
        return self::isPrimaryHost($host) || self::isPlatformDomain($domain);
    }

    /**
     * Domain types that may serve the public clinic visitor API.
     *
     * @return list<string>
     */
    public static function visitorDomainTypes(): array
    {
        return ['tenant_subdomain', 'custom_domain', 'primary_domain'];
    }
}
