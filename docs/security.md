# Security

Security priorities for the dashboard and public MVP:

- Resolve public tenant context from host only.
- Keep host-to-tenant lookup centralized through `TenantService`.
- Scope tenant-owned records by `tenant_id`.
- Keep visitor sessions separate from dashboard authentication.
- Store OTP values only as hashes.
- Make OTP records expiring and single-use.
- Rate-limit OTP requests and verification attempts.
- Invalidate OTP records after too many failed attempts.
- Store uploads and generated assets privately.
- Authorize dashboard actions with policies.
- Never rely only on hidden frontend controls.
- Do not expose storage paths or raw provider errors.
- Encrypt SMS provider credentials.
- Mask SMS credentials in dashboard views.
- Store safe SMS previews and hashes, not plain OTP message bodies.
- Restrict private SMS provider accounts to their owner.
- Scope SMS provider filter options so users cannot discover another user's
  private provider names through dashboard logs.

Sensitive events such as admin OTP requests and successful admin verification
are logged without plain OTP values.
