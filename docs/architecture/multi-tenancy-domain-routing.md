# Multi-Tenancy Domain Routing

## Principle

Tenant identity is resolved only from the request host.

Never accept `tenant_id`, tenant slug, or tenant domain from visitor-controlled
request bodies as authority for data access.

## Tables

- `tenants`
- `tenant_domains`

`tenant_domains.domain` stores the exact lowercase host that should resolve to a
tenant.

## Middleware

Class:

```text
App\Http\Middleware\ResolveTenantByHost
```

Behavior:

1. Read `$request->getHost()`.
2. Lowercase the host.
3. Find an active `tenant_domains` row.
4. Ensure the related tenant is active.
5. Store the tenant in the service container as `currentTenant`.
6. Store the tenant on request attributes.
7. Abort with 404 when host or tenant is not active.

Local development may fall back from `localhost` or `127.0.0.1` to the first
active tenant, but tests and production should use explicit tenant hosts.

## Query Rules

Every tenant-owned public query must include:

```php
where('tenant_id', $tenant->id)
```

For visitor-specific resources after OTP verification, also include:

```php
where('lead_id', $sessionLeadId)
```

This applies to:

- uploads
- generation groups
- generation jobs
- generated assets
- tenant AI settings
- tenant theme settings
- audit logs

## Required Tests

- Known tenant host resolves successfully.
- Unknown host returns 404.
- Tenant A cannot access Tenant B uploads, generation groups, or assets.
- A visitor session for one lead cannot access another lead's assets.
