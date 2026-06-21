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
- Never rely only on hidden frontend controls.
- Do not expose storage paths or raw provider errors.

Sensitive events such as admin OTP requests and successful admin verification
are logged without plain OTP values.
