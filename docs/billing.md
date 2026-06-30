# Billing

Billing uses the existing invoice and payment flow with clinic-aware records.
Invoices and payments include `tenant_id` where the purchase belongs to a clinic.

Flow:

1. `PlanController` resolves the active admin clinic from the request context.
2. `BillingService::invoiceForPlan` validates purchase rules.
3. A pending invoice is created for the selected plan and clinic.
4. Payment is initiated through `PaymentGatewayService`.
5. `BillingService::verifyPayment` marks the payment and invoice paid.
6. `SubscriptionActivationService` activates the invoice exactly once.

Payment verification is idempotent. `invoices.activated_at` prevents duplicate
subscription activation or duplicate credit grants when callbacks are repeated.

Activation behavior depends on plan type:

- Base subscription: replace any active base subscription for the clinic.
- Service add-on: attach missing service entitlement to the current base.
- Credit top-up: add purchased credit to an existing service entitlement.

Normal clinic users see only their own clinic invoices. Users with billing
management permission can view broader billing data, optionally filtered by the
selected clinic context.
