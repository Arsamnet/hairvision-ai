# Credits

HairVision uses a ledger-style service credit system. Current balances are kept
on `subscription_service_entitlements`, and every grant, top-up, and usage write
is recorded in `credit_transactions`.

Ledger transaction types:

- `grant`: initial base-plan or add-on credit
- `top_up`: purchased extra service credit
- `use`: successful AI service usage
- `refund`: reserved for future refund flows
- `adjustment`: reserved for authorized manual corrections

`CreditLedgerService` consumes credit inside a database transaction and locks the
entitlement row before updating `used_credit`. This prevents negative credit and
double spending under concurrent generation jobs.

Credit exhaustion blocks new AI service requests for that service only. It does
not expire the base subscription or block historical reports. The Persian notice
for users is stored in `lang/fa/credits.php`.
