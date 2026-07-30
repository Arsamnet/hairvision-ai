# AI Agent Instructions

Version: [`2.1.0`](../CHANGELOG.md#210)

This file is the compact operating prompt for AI agents. The primary guide is
`AGENTS.md`; this file keeps the same rules inside `/docs`.

## Reading Order

Read before editing code:

1. `AGENTS.md`
2. `docs/business-rules-fa.md`
3. `docs/architecture.md`
4. `docs/api.md`
5. `CHANGELOG.md`

The documentation set is intentionally consolidated. Do not create a new docs
file for a small topic; update one of the canonical docs instead.

## Product Summary

HairVision is a complete Laravel 13 multi-tenant SaaS for clinic AI image
previews. Public visitors enter through a tenant host, verify their mobile
number by OTP, keep a private current source image, and request eligible AI
outputs from system-managed categories such as hair transplant, eyebrow
transplant, or dynamically added services like under-eye filler.

## Core Rules

- Laravel 13 conventions, API-first architecture, thin controllers.
- Public Blade calls `/api/v1`; it must not duplicate business logic.
- Public UI is Persian, RTL, and uses the embedded Vazirmatn font.
- Visitor shell has full-width tenant header, HairVision app body, and tenant
  footer.
- No testing/setup/deployment/debugging copy in visitor templates.
- No arbitrary external frontend resources. Tenant logos/theme images may be
  loaded only from the primary HairVision domain.
- Tenant is resolved from host only; never trust visitor-submitted tenant ids,
  slugs, or domains.
- Tenant-owned records include `tenant_id`; visitor-owned resources also scope
  by current verified lead.
- OTP codes are stored only as hashes and never returned, logged, seeded, or
  displayed in plain text.
- Uploads and generated assets stay under private storage and stream only
  through scoped routes.
- Seeded/new model input processing defaults to an exact 800-pixel longest side
  and JPEG quality 85; generated results are always normalized to the same
  private JPEG profile before permanent storage.
- AI provider logic stays behind services/actions/jobs/provider adapters, never
  controllers.
- The local `sample_image` adapter may run only through an active model access
  assigned to the clinic. It must not call external services, generate images,
  mutate images, or use external URLs.
- Real providers require active assigned AI model access, encrypted credentials,
  active subscription, service entitlement, and remaining service credit.
- OpenAI image edits use the code-owned `openai` adapter, multipart `image[]`,
  a server-side category-output prompt, and private base64 result decoding.
- OpenAI DNS/TCP failures proven to occur before HTTP delivery use bounded,
  environment-configured retry attempts with backoff. Each retry is a new
  generation attempt; ambiguous response timeouts are never repeated
  automatically.
- Fresh seed data enables only category-specific OpenAI providers, access
  records, executable models, clinic assignments, and plan mappings. Seeded
  `sample_image` and `gapgpt` records remain available for explicit management
  but are inactive and are never attached to a seeded plan.
- Credit is consumed atomically only after successful provider execution.
- The seed keeps the system `local` debug/test SMS gateway enabled/default and
  protected from updates or deletion. IPPanel remains disabled/non-default,
  and no tenant-specific SMS gateway is seeded for the demo clinic.
- Shared cPanel hosting uses the database queue, scheduler watchdog, and
  detached PHP workers as its baseline; a non-overlapping window worker is the
  explicit fallback when detachment is unavailable. A fallback window is
  shortened to the time remaining in its current cron minute so its overlap
  lock is released before the next tick. Redis/Horizon is the
  upgrade path only where a persistent process manager exists. Keep provider
  concurrency independent from worker count, preserve
  `provider timeout < worker timeout < retry_after`, and do not run both pools.
- Do not introduce React, Vue, Inertia, Livewire, Next.js, CDN assets, or
  external dashboard icon packs unless explicitly requested.
- Add/update tests for every behavior change and run the full test suite.
- System landing templates are trusted HTML fragments managed only by authorized
  platform users. Template CSS and JavaScript are inline; external template
  assets, scripts, stylesheets, and fonts remain prohibited.
- The clinic dashboard guide is system-owned relational content stored in
  `guide_pages`, `guide_sections`, `guide_steps`, and `guide_faqs`. Every
  authenticated clinic role can read it, including without an active plan;
  only `guide.manage` can edit it through the two dedicated management pages.
- The guide and FAQ are separate clinic-only help pages with distinct local
  menu icons. Keep categorized anchor indexes, smooth in-page navigation, and
  native accessible FAQ accordions that honor reduced-motion preferences.
- Guide titles, audiences, step titles, and questions are escaped plain text.
  Editable descriptions and answers use the reusable WYSIWYG/source editor and
  must pass `SimpleRichTextSanitizer` before storage and every render. Preserve
  selection across toolbar actions, normalize browser-generated formatting to
  semantic allowlisted HTML, and sanitize pasted/source HTML. The one named
  textarea is also the visible no-JavaScript fallback and is the sole submitted
  source of truth.
- Dashboard fragment links must scroll only `[data-admin-scroll-container]`.
  Keep the admin body fixed to the viewport, suppress native initial-fragment
  scrolling, reset root scroll offsets, and honor reduced-motion preferences.

## Reuse Requirements

- Digits: `App\Support\NumberNormalizer` and `App\Rules\SupportedDigits`.
- Jalali dates: `App\Support\JalaliDate`, `App\Rules\JalaliDate`, and
  `App\Rules\JalaliDateTime`.
- Pagination: `App\Support\Pagination\PaginationParameters` and
  `App\Support\Pagination\PaginatedResponse`.
- Search: `App\Support\Query\SearchFilter`; date filters:
  `App\Support\Query\DateRangeFilter`.
- Admin lists: `x-admin.list-filters`,
  `x-admin.table.sortable-heading`, `x-admin.table.pagination-footer`,
  `x-admin.table.action-menu`, and mobile-list components where important.
- Dashboard table rows with a view action open that view page on row click,
  show a pointer cursor, and use "view" copy instead of "details" in row
  action menus.
- Dashboard icons: local `x-admin.icon` with UIcons regular-rounded classes.
- Logging: ordered local debug traces are registered centrally through
  `DebugStepServiceProvider` and `LogDebugSteps`; do not add per-file logger
  imports for ordinary controllers, services, jobs, or models. Never log
  sensitive data.

## Implementation Checklist

- Resolve tenant from host and abort unknown hosts with `404`.
- Keep public lead session separate from clinic/platform admin sessions.
- Validate with Form Requests where inputs are non-trivial.
- Authorize dashboard routes with policies and `permission:*` middleware.
- Enforce active plan/service access through backend middleware/services, not UI
  locks alone.
- Keep GapGPT image edits multipart with file field `image`, `model =
  gpt-image-2`, `n = 1`, `response_format = url`, and `size = 1024x1024`.
- Keep OpenAI image edits multipart with file field `image[]`, a configurable
  API URL and model, the selected application-output prompt, `n = 1`, and
  base64 result handling. Do not send `response_format`; GPT Image returns
  base64 image data without that request field. Seeded defaults are `quality =
  low`, `size = 816x816`, `output_format = jpeg`, `background = opaque`,
  `moderation = auto`, `output_compression = 70`, response timeout `1000`, and
  connection timeout `50` seconds.
- The default OpenAI executable capacity is two concurrent requests and follows
  `HAIRVISION_OPENAI_CONCURRENCY_LIMIT`; worker and global provider capacity
  must remain at least that high before increasing it.
- Keep the provider request size separate from private asset normalization:
  provider inputs and persisted results use the 800px/JPEG 85 product default.
- Keep structured production provider-error logs complete enough to correlate
  the generation, attempt, model, worker, timings, HTTP status/request id, cURL
  code, retry decision, and redacted exception chain. Never include the API key,
  prompt, image bytes, private path, raw payload/response, or stack trace.
- Never seed, commit, log, display, or document a real GapGPT or OpenAI API key.
- Keep user-facing errors localized and generic; log only safe operational
  context.

## Required Commands

```bash
php artisan test
composer phpdoc:audit
```

Run this too after CSS, JavaScript, Blade, font, or Vite changes:

```bash
npm run build
```

## Release Discipline

Every change without an assigned version belongs under `Unreleased` in the root
`CHANGELOG.md`. Preparing a release moves those entries to one dated SemVer
section and updates runtime, environment-example, documentation-header, and
version-test metadata together. Historical release sections are not rewritten
for later work.
