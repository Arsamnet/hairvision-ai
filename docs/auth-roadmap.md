# Authentication Roadmap

Status: admin OTP and lead dashboard implemented for the dashboard phase.

This document now tracks remaining authentication and dashboard work after the
initial `/admin` dashboard implementation.

## Goals

- Tenant admins can sign in to a protected dashboard for their clinic.
- Platform admins can manage tenants, domains, provider settings, and support
  tasks.
- Future client users can access their own lead/result history if the product
  chooses to expose a client portal.
- Authorization stays tenant-scoped and policy-driven.
- Public visitor OTP flow remains separate from dashboard authentication.

## Authentication Model

Use Laravel authentication conventions with explicit guards:

- `web`: default browser guard for authenticated dashboard users.
- `sanctum`: API token/session support for first-party dashboard APIs when
  needed.
- `visitor`: do not create as a Laravel auth guard for Phase 1; keep the
  existing verified-lead session flow unless a real client portal is started.

Recommended user ownership fields for dashboard users:

- `users.tenant_id` nullable for platform admins and required for tenant admins.
- `users.role` or a normalized roles table when permissions become dynamic.
- `users.status` for active, invited, suspended, and disabled accounts.

## Role-Based Access Control

Start with explicit roles, then graduate to permission tables only when the
dashboard needs tenant-customizable permissions.

Recommended roles:

- `platform_admin`: manages all tenants, domains, provider catalog, and support
  access.
- `tenant_owner`: manages one tenant, billing/profile settings, users, and
  provider settings.
- `tenant_admin`: manages tenant leads, uploads, notes, stages, and exports.
- `tenant_viewer`: read-only access to tenant leads and generated assets.
- `client`: optional future role for visitor-owned result history.

Initial permissions:

- `tenants.viewAny`
- `tenants.view`
- `tenants.update`
- `tenantUsers.manage`
- `leads.viewAny`
- `leads.view`
- `leads.updateStage`
- `leadNotes.create`
- `uploads.view`
- `generatedAssets.view`
- `exports.create`
- `themeSettings.update`
- `providerSettings.update`
- `auditLogs.view`

## Protected Routes

Use separate route groups to keep public MVP routes clean:

- `routes/web.php` remains the public tenant landing shell.
- `routes/api.php` keeps `/api/v1` public visitor endpoints.
- Dashboard routes live under `/admin`.
- Future dashboard APIs may live under `/api/v1/dashboard`.
- Add future platform routes under `/platform`.

Suggested middleware:

- `auth`
- `verified` if email verification is required.
- `tenant.user` to require the authenticated user to belong to the resolved
  tenant for tenant dashboards.
- `can:*` policy checks on every tenant-owned dashboard resource.
- `throttle` on login, invitation, export, and sensitive write endpoints.

## Tenant Resolution

Keep host-based tenant resolution as the source of tenant identity. Dashboard
APIs must not trust `tenant_id` from request bodies. Platform admin routes may
select a tenant by route parameter only after platform authorization succeeds.

Tenant admin routes should scope queries by:

```php
where('tenant_id', $request->user()->tenant_id)
```

Platform admin routes should explicitly authorize cross-tenant access through
policies before querying or mutating records.

## Policies

Create policies before implementing dashboard controllers:

- `TenantPolicy`
- `LeadPolicy`
- `LeadNotePolicy`
- `UploadPolicy`
- `GenerationGroupPolicy`
- `GeneratedAssetPolicy`
- `TenantThemeSettingPolicy`
- `TenantAiSettingPolicy`
- `AuditLogPolicy`
- `UserPolicy`

Policy requirements:

- Deny by default.
- Platform admins may cross tenant boundaries only through explicit abilities.
- Tenant roles can only access records with matching `tenant_id`.
- Client users can only access their own lead-owned records.
- Every write ability should name the side effect, such as `updateStage`,
  `createNote`, or `updateProviderSettings`.

## Middleware

Planned middleware:

- `EnsureTenantUserBelongsToResolvedTenant`
- `EnsurePlatformAdmin`
- `EnsureClientOwnsLeadSession` if a client portal is implemented.
- `RecordAuditLog` for dashboard write actions.

Middleware must complement policies, not replace them.

## Dashboard Modules

Tenant admin dashboard:

- Lead list with stage filters.
- Lead detail with phone, uploads, generation groups, generated assets, and
  private asset streaming.
- Lead stage editing.
- Admin notes.
- CSV export.
- Theme settings.
- Provider settings with encrypted secrets.
- Audit log view.

Client dashboard or portal:

- Authentication decision pending: passwordless OTP, email magic link, or user
  account.
- Lead/result history scoped to the authenticated client.
- Download/share controls only after explicit product and privacy review.

Platform dashboard:

- Tenant CRUD.
- Domain verification and status.
- Provider catalog and system defaults.
- Support audit trail.

## Versioning Plan

- `1.0.x`: public visitor MVP hardening only.
- `1.1.0`: tenant admin authentication, lead dashboard, role-aware edits, and
  primary product landing page.
- `1.2.0`: exports, richer audit logs, editable settings, and full user CRUD.
- `1.3.0`: tenant user management, theme settings, and provider settings.
- `2.0.0`: client portal or breaking dashboard/API changes if required.

## Required Tests Before Implementation

- Dashboard login throttling and failed-login behavior.
- Tenant admin cannot access another tenant's leads, uploads, assets, notes, or
  settings.
- Platform admin cross-tenant access is policy-authorized and audited.
- Tenant viewer cannot mutate lead stages, notes, provider settings, or theme.
- Tenant admin cannot set `tenant_id` through request payloads.
- Client user cannot access another client's lead or assets.
- Policies are covered for every dashboard-owned model.
- Protected routes redirect or return `401/403` consistently.
