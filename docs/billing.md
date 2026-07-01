# Billing

Billing uses the existing invoice and payment flow with clinic-aware records.
Invoices and payments include `tenant_id` where the purchase belongs to a clinic.

Flow:

1. `PlanController` resolves the active admin clinic from the request context.
2. `BillingService::invoiceForPlan` validates purchase rules.
3. A pending invoice is created for the selected plan and clinic.
4. Payment is initiated through `PaymentGatewayService`.
5. The browser is redirected to the selected gateway payment URL.
6. The gateway returns to `admin.billing.verify` with its callback payload.
7. `BillingService::verifyPayment` marks the payment and invoice paid.
8. `SubscriptionActivationService` activates the invoice exactly once.

The default seeded test gateway is Zarinpal sandbox. It uses the sandbox request,
verify, and `StartPay` URLs stored on the `zarinpal` payment gateway row. The
invoice pay route accepts browser navigation and form submits, then redirects to
Zarinpal instead of activating the invoice inside the initial request.

Payment verification is idempotent. `invoices.activated_at` prevents duplicate
subscription activation or duplicate credit grants when callbacks are repeated.

Activation behavior depends on plan type:

- Base subscription: replace any active base subscription for the clinic.
- Service add-on: attach missing service entitlement to the current base.
- Credit top-up: add purchased credit to an existing service entitlement.

Normal clinic users see only their own clinic invoices. Users with billing
management permission can view broader billing data, optionally filtered by the
selected clinic context.

Payment gateway errors must be caught, logged through Laravel logging with safe
context such as invoice/payment ids, and returned to the user only as a generic
localized message. Raw gateway payloads, exception messages, credentials,
authority values, stack traces, and provider diagnostics must not be displayed in
Blade or JSON responses.
