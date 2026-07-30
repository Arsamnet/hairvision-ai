# HairVision

Version: [`2.1.0`](CHANGELOG.md#210)

HairVision is a complete Laravel 13 multi-tenant SaaS for clinics offering
AI-assisted image previews. Visitors arrive through a clinic subdomain or
custom domain, verify their mobile number by OTP, become a tenant-scoped lead,
maintain a private current source image, and independently request eligible
outputs from system-managed AI categories.

The public product UI is Persian, right-to-left, and Blade-based. All business
behavior is API-first: Blade renders the shell and calls `/api/v1` endpoints for
OTP, upload, generation, status, results, and private asset loading.

## Documentation

The documentation set is intentionally small. Do not add a new documentation
file unless a separate lifecycle or audience is genuinely needed.

- [AI Agent Instructions](docs/agent-instructions.md)
- [Persian Product and Business Rules](docs/business-rules-fa.md)
- [Engineering Architecture](docs/architecture.md)
- [API Reference](docs/api.md)
- [Release Notes](CHANGELOG.md)

`AGENTS.md` is the concise operating guide for AI agents and points to the same
canonical references.

## Version 2.1.0 Includes

- Laravel 13, PHP 8.3+, Blade, Tailwind CSS, Vite, Sanctum, Redis, Horizon.
- Host-based tenant resolution and strict tenant isolation.
- Public Persian RTL visitor flow with embedded Vazirmatn font.
- Hashed, expiring, rate-limited OTP for public leads and dashboard users.
- Private, versioned source-image and generated-asset storage.
- System-managed AI categories and independently requestable outputs.
- Database-backed model queue coordination, capacity limits, and credit
  reservations.
- Database-queue worker supervision, provider-wide concurrency leases, safe
  OpenAI connection retries, and resumable generation status polling.
- Lossless unsigned BIGINT plan-price persistence for rial amounts above the
  32-bit signed integer range.
- Provider-agnostic AI model access with local input/output JPEG normalization.
- Local `sample_image` model access assigned per clinic by a system admin.
- Assigned GapGPT image-edit access through encrypted dashboard credentials.
- Configurable OpenAI image-edit access with encrypted credentials, multipart
  `image[]` uploads, category-output prompts, and private base64 result handling.
- OpenAI-only active seed mappings for executable models, clinic assignments,
  and base/add-on plans, with inactive sample and GapGPT management records.
- Protected system debug/test SMS gateway behavior and tenant-safe lead image
  history with an accessible private-asset gallery.
- Admin dashboard under `/admin` with OTP login, roles, permissions, tenant
  context, leads, CRM follow-ups, users, clinics, AI models, plans, billing,
  gateways, reports, and system settings.
- Persisted light/dark dashboard themes with neutral surfaces, balanced
  violet/teal logo colors, matching logo variants, and theme controls on
  dashboard authentication screens.
- Permission-aware administrator navigation grouped under security, finance,
  reports, and settings with matching hierarchical URLs, shared breadcrumb
  metadata, Persian permission labels, and distinct local icons for each
  management area.
- A clinic-only help center with separate user-guide and FAQ pages, plus
  permission-controlled system management for structured guide content.
- System-managed landing templates with trusted inline HTML/CSS/JavaScript,
  theme-aware code editing, one active default for new clinics, and explicit
  per-clinic assignment.
- SaaS plan, subscription, service entitlement, and credit ledger behavior.
- Lead-aware AI usage reporting and tenant-scoped processing history.
- Jalali/Shamsi business dates, Persian localization, and reusable dashboard UI
  components.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Map local hosts:

```text
127.0.0.1 arya.hairvision-ai.test
127.0.0.1 hairvision-ai.test
```

Run locally:

To reproduce the database-backed cPanel scheduler and queue behavior from the
VS Code terminal on macOS, keep this command running:

```bash
./scripts/cpanel-queue-local
```

Use `./scripts/cpanel-queue-local --once` for one cron-equivalent tick or
`./scripts/cpanel-queue-local --list` to inspect the coordinator and worker
lanes. Stop watch mode with `Ctrl+C`. The runner overrides queue settings only
for its own process, clears a stale local config cache, and does not modify
`.env`.

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

Open:

```text
http://arya.hairvision-ai.test:8000
http://hairvision-ai.test:8000/admin
```

## cPanel Queue Worker

Start from the checked-in production template instead of copying the local
development environment verbatim:

```bash
cp .env.cpanel.example .env
```

The template assumes the canonical `hairvision.ir` host and the checked-in
`/home/hairvision/...` deployment paths. Change the host and cPanel-prefixed
database values when the target account differs, fill all blank secrets only on
the server, and generate `APP_KEY` once for a fresh production installation.

Use Horizon as the primary production worker. If shared cPanel hosting does not
support a persistent worker, set `QUEUE_CONNECTION=database`, set
`HAIRVISION_CPANEL_QUEUE_CRON_ENABLED=true`, keep both queue retry windows above
`HAIRVISION_QUEUE_WORKER_TIMEOUT`, and run this cPanel Cron Job every minute:

```cron
* * * * * /usr/local/bin/php /home/CPANEL_USER/path/to/artisan schedule:run >> /home/CPANEL_USER/path/to/storage/logs/cron.log 2>&1
```

Do not enable the cPanel fallback while Horizon is running. Full environment and
timeout settings are documented in [Engineering Architecture](docs/architecture.md#shared-cpanel-queue-fallback).

## Quality Gate

After every behavior change:

1. Add or update focused tests.
2. Run the full test suite.
3. Run the asset build when CSS, JavaScript, Blade, fonts, or Vite inputs
   change.
4. Keep documentation changes inside the existing core docs unless a new file is
   clearly justified.

Required commands:

```bash
php artisan test
composer phpdoc:audit
npm run build
```
