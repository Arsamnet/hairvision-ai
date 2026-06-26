# Security

Security priorities for the dashboard and public MVP:

- Resolve public tenant context from host only.
- Scope tenant-owned records by `tenant_id`.
- Keep visitor sessions separate from dashboard authentication.
- Store OTP values only as hashes.
- Make OTP records expiring and single-use.
- Rate-limit OTP requests and verification attempts.
- Invalidate OTP records after too many failed attempts.
- Store uploads and generated assets privately.
- Authorize dashboard actions with policies.
- Authorize system-management routes with `permission:*` middleware.
- Enforce normal-user plan access with `active_plan` middleware on dashboard
  routes. UI locks are not the authority.
- Never rely only on hidden frontend controls.
- Do not expose storage paths or raw provider errors.
- Stream lead upload previews only through authorized dashboard routes after
  lead policy, tenant-context, lead/upload ownership, and private storage checks.
- Store SMS and payment gateway credentials with encrypted casts.
- Hide gateway credentials from Blade/API serialization.
- Keep user-private SMS gateways scoped to their owner.
- Scope invoice and subscription queries by authenticated user unless the user
  has explicit billing-management permission.
- Make payment verification idempotent before activating plans.
- Replace any previous active subscription during successful payment
  verification without carrying remaining time or usage credit.

Sensitive events such as admin OTP requests and successful admin verification
are logged without plain OTP values.
