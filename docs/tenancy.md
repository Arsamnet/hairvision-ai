# Tenancy

Tenant identity for public visitor routes is resolved from the request host.
Visitor-controlled request data must never be trusted as tenant authority.

The admin dashboard uses authenticated users plus an active tenant stored in the
session. A user can only select a tenant if one of these is true:

- The user is a `super_admin`.
- The user has an active `tenant_user` membership for that tenant.

Tenant-owned dashboard queries are scoped server-side. Lead lists, lead detail
pages, updates, notes, users, reports, uploads, generation jobs, and generated
assets must remain tenant-aware.

Super admins may select "all clinics" in the dashboard switcher. Clinic users
operate inside one active clinic at a time.
