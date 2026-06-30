# Plans

HairVision plans are purchasable SaaS products. Each plan has a stable
`plan_type`:

- `base_subscription`: the main clinic subscription.
- `service_add_on`: adds a missing AI service to the active base subscription.
- `credit_top_up`: adds credit to an already enabled service.

Plan service credits are stored in `plan_service_entitlements`. Each row defines
the service key and credit amount a plan grants. The current service keys are
`hair_transplant` and `eyebrow_transplant`; display labels come from
`lang/fa/services.php`.

## Base Plans

A clinic can have only one active base subscription at a time. Buying a different
base plan is an upgrade/change flow: the previous active base subscription is
marked `replaced`, and the new subscription starts after payment activation.
Remaining time and remaining credit are not carried unless a later documented
business rule changes this.

Buying the same active base plan again is blocked while that base subscription is
still active. If service credit is exhausted, the clinic should buy a credit
top-up package, not the same base plan.

## Service Add-ons

Service add-ons require an active base subscription. They attach a new
`subscription_service_entitlements` row to the current base subscription and
inherit the base subscription end date. Duplicate add-ons for an already active
service are blocked by backend validation.

## Credit Top-ups

Credit top-ups are not time based. They do not change `subscriptions.ends_at` and
do not unlock services. A top-up can be purchased only for a service the clinic
already has through the active base subscription or an add-on.
