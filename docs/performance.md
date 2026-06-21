# Performance

Dashboard performance rules:

- Paginate lead lists.
- Use tenant-scoped indexes for lead filters and reporting.
- Eager load assigned users, notes, uploads, generation groups, and jobs on
  detail pages where needed.
- Keep dashboard Blade and public landing assets local.
- Avoid loading unrelated dashboard code into public visitor workflows.
- Keep queue-heavy generation work outside HTTP with Redis Queue and Horizon
  outside tests.

Current indexes support common dashboard filters:

- `tenant_id + status`
- `tenant_id + crm_stage`
- `tenant_id + created_at`
- `tenant_id + assigned_user_id`
- `tenant_user` access lookups
