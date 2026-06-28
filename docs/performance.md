# Performance

Dashboard performance rules:

- Paginate lead lists.
- Use reusable pagination helpers for JSON and dashboard-backed lists.
- Enforce `HAIRVISION_API_MAX_PER_PAGE` on shared pagination paths.
- Use tenant-scoped indexes for lead filters and reporting.
- Eager load assigned users, notes, uploads, generation groups, jobs, and assets
  on lead detail pages where needed.
- Keep dashboard Blade and public landing assets local.
- Avoid loading unrelated dashboard code into public visitor workflows.
- Keep queue-heavy generation work outside HTTP with Redis Queue and Horizon
  outside tests.
- Cache system settings and clear the cache after updates.
- Keep management report queries aggregate-based.

Current indexes support common dashboard filters:

- `tenant_id + status`
- `tenant_id + crm_stage`
- `tenant_id + created_at`
- `tenant_id + assigned_user_id`
- `tenant_user` access lookups
