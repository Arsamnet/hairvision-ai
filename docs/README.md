# HairVision Documentation

This directory is the canonical documentation set for HairVision `1.0.0`.
The root `README.md` is the project entry point; this file expands the map for
development, architecture, roadmap, and audit work.

## Product

- `product/scope-mvp.md` - Phase 1 product scope, public visitor flow, and
  explicit out-of-scope items.
- `product/data-model.md` - Phase 1 tables, tenant ownership rules, and schema
  invariants.

## Architecture

- `architecture/api-first-contract.md` - `/api/v1` public API contract and
  versioning rules.
- `architecture/multi-tenancy-domain-routing.md` - host-based tenant resolution
  and tenant query rules.
- `architecture/ai-provider-adapters.md` - provider abstraction, DTOs, job flow,
  and future provider strategy.
- `architecture/sample-image-provider-mvp.md` - local sample provider behavior
  for version `1.0.0`.
- `architecture/security-threat-model.md` - MVP security risks and required
  controls.
- `architecture/whm-cpanel.md` - deployment shape for WHM/cPanel environments.

## Development

- `development/ai-agent-context.md` - mandatory context for AI coding agents.
- `development/tech-stack-requirements.md` - runtime, package, infrastructure,
  and configuration baseline.
- `development/vscode-codex-start.md` - local setup and agent prompt guide.
- `development/testing-and-release-checklist.md` - test commands and release
  readiness checklist.

## Roadmap

- `roadmap/admin-client-dashboard-auth.md` - planned admin/client dashboard,
  authentication, authorization, guards, policies, middleware, and permissions.

## Audit

- `audit/cleanup-report.md` - cleanup decisions, removed files, uncertain
  candidates, and follow-up recommendations.
