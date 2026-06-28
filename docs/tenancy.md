# Tenancy

Tenant identity for public visitor routes is resolved from the request host.
Visitor-controlled request data must never be trusted as tenant authority.

The admin dashboard uses authenticated users plus server-side tenant membership
checks. Page-level clinic filters such as `clinic_id` are accepted only when one
of these is true:

- The user is a `super_admin`.
- The user has an active `tenant_user` membership for that tenant.

Tenant-owned dashboard queries are scoped server-side. Lead lists, lead detail
pages, updates, notes, users, reports, uploads, generation jobs, and generated
assets must remain tenant-aware.

Super admins see all clinics by default on clinic-owned lists and may filter
inside the page content. Clinic users see only records for clinics where they
have an active membership.
