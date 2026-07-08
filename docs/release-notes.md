# Release Notes

## 1.0.0

HairVision `1.0.0` is the first complete release of the Laravel 13
multi-tenant SaaS for hair-transplant clinics.

### Product

- Tenant-hosted public visitor flow.
- Persian RTL Blade public UI with embedded Vazirmatn font.
- Tenant header, HairVision app body, tenant footer, and optional back-to-site
  link.
- Public OTP verification, private upload, staged generation, polling, result
  gallery, original upload preview, and zoomable assets.
- Landing page visit tracking before lead creation.

### Platform and Dashboard

- Admin dashboard under `/admin` with mobile OTP login.
- Separate public lead, clinic admin, and platform admin session cookies.
- Role-aware dashboards for clinic and platform contexts.
- Lead list/detail/create/edit, CRM follow-ups, private upload streaming, users,
  clinics, reports, settings, roles, permissions, gateways, billing, plans, and
  system management.
- Persian localization files under `lang/fa`.
- Default-light dashboard theme with persisted browser theme choice.
- Two-mode responsive strategy: mobile/tablet below `1024px`, desktop from
  `1024px`.
- Shared dashboard cards, badges, empty states, modals, list filters, sortable
  headings, pagination footers, action menus, and mobile list cards.
- Dashboard rows with permitted view actions open the view page on row click
  and use view wording for row action menus.
- Local UIcons regular-rounded dashboard icon surface through `x-admin.icon`.

### Tenancy and Security

- Host-based tenant resolution with unknown-host `404`.
- Tenant-scoped public and dashboard queries.
- Hashed, expiring, single-use, rate-limited OTP records.
- Private upload and generated asset storage.
- Scoped public asset streaming and authorized dashboard upload previews.
- Policy-backed dashboard authorization and `permission:*` route middleware.
- Active-plan middleware for normal-user operational routes.
- Encrypted SMS, payment, and AI model credentials with masked display.
- Seeded system SMS gateways use local SMS as the enabled default; IPPanel is
  seeded disabled and non-default until explicitly activated.
- Safe provider/gateway logging without OTPs, credentials, private paths, raw
  payloads, or stack traces.

### AI Generation

- Provider-agnostic AI access layer with config-backed model definitions.
- Protected local `sample_image` provider as the default provider.
- Local sample assets for `two_weeks`, `intermediate_regrowth`, and
  `final_result`.
- GapGPT image-edit provider behind encrypted AI model access records and clinic
  assignment.
- GapGPT multipart request shape with `image`, `model = gpt-image-2`, `prompt`,
  `n = 1`, `response_format = url`, and `size = 1024x1024`.
- AI model usage logs with safe metadata.
- Clinic AI model assignment enforcing one active access per usage category.

### Billing and Entitlements

- Plan types for base subscriptions, service add-ons, and credit top-ups.
- Service entitlement definitions on plans.
- Clinic subscriptions with service-level credit balances.
- Immutable credit ledger rows for grants, top-ups, use, refunds, and
  adjustments.
- Atomic service credit consumption after successful provider execution.
- Zarinpal sandbox payment adapter and deterministic local adapter for tests.
- Idempotent payment verification and one-time invoice activation.

### Engineering

- API-first `/api/v1` public endpoints.
- Redis Queue and Horizon production queue shape.
- Jalali/Shamsi business-date storage, validation, display, and filtering.
- Shared digit normalization for Persian, Arabic, and Latin digits.
- Shared pagination, search/filter, date range, dashboard overview, billing,
  entitlement, credit ledger, AI model, and debug logging utilities.
- Consolidated documentation set:
  - `docs/agent-instructions.md`
  - `docs/business-rules-fa.md`
  - `docs/architecture.md`
  - `docs/api.md`
  - `docs/release-notes.md`

### Quality

- Feature tests cover tenant resolution, unknown hosts, landing visits, OTP
  security, digit normalization, upload validation, generation, private assets,
  tenant isolation, dashboard auth, roles, permissions, billing, service
  entitlements, credit ledger, AI model access, GapGPT request shape, Jalali
  dates, localization keys, shared dashboard components, and responsive
  contracts.

Required release checks:

```bash
php artisan test
npm run build
```
