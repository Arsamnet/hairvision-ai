# Subscriptions

Subscriptions are clinic-owned base-plan entitlements. Dashboard purchases are
initiated by an authenticated user, but the active subscription is scoped to the
selected clinic tenant through `subscriptions.tenant_id`.

The active base subscription controls:

- dashboard operational access for normal clinic users
- AI service availability period
- expiration behavior by `starts_at` and `ends_at`
- the parent record for service entitlements and credit ledger rows

Service access is stored in `subscription_service_entitlements`:

- `source_type = base_plan` for services included by the base plan
- `source_type = add_on` for services bought later
- `included_credit` for base/add-on grants
- `purchased_credit` for top-ups
- `used_credit` for consumed service credits

When the base subscription expires, AI services stop even if unused service
credit remains. Dashboard overview, plans, billing, and settings remain
available according to the existing `active_plan` middleware rules.
