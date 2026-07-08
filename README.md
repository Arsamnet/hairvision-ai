# HairVision

Version: `1.0.0`

HairVision is a complete Laravel 13 multi-tenant SaaS for hair-transplant
clinics. Visitors arrive through a clinic subdomain or custom domain, verify
their mobile number by OTP, become a tenant-scoped lead, upload a private
face/scalp image, and receive three staged preview results:

- `two_weeks`
- `intermediate_regrowth`
- `final_result`

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
- [Release Notes](docs/release-notes.md)

`AGENTS.md` is the concise operating guide for AI agents and points to the same
canonical references.

## Version 1.0.0 Includes

- Laravel 13, PHP 8.3+, Blade, Tailwind CSS, Vite, Sanctum, Redis, Horizon.
- Host-based tenant resolution and strict tenant isolation.
- Public Persian RTL visitor flow with embedded Vazirmatn font.
- Hashed, expiring, rate-limited OTP for public leads and dashboard users.
- Private upload and generated-asset storage.
- Provider-agnostic AI model access layer.
- Protected local `sample_image` provider as the default provider.
- Optional assigned GapGPT image-edit access through encrypted dashboard
  credentials.
- Admin dashboard under `/admin` with OTP login, roles, permissions, tenant
  context, leads, CRM follow-ups, users, clinics, AI models, plans, billing,
  gateways, reports, and system settings.
- SaaS plan, subscription, service entitlement, and credit ledger behavior.
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
127.0.0.1 aria.hairvision-ai.test
127.0.0.1 hairvision-ai.test
```

Run locally:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

Open:

```text
http://aria.hairvision-ai.test:8000
http://hairvision-ai.test:8000/admin
```

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
npm run build
```
