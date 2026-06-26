# SMS

HairVision centralizes SMS delivery behind provider accounts, a resolver, and
safe delivery logs. SMS is used by public visitor OTP, admin OTP, account test
sends, and future dashboard notifications.

## Provider Accounts

Provider account scopes:

- `system`: created by a super admin and available to users only when active.
- `user`: created by one dashboard user and available only to that owner when
  active.

Supported provider types:

- Melli Payamak / ملی پیامک: `melli_payamak`
- KaveNegar / کاوه نگار: `kavenegar`
- IPPanel: `ippanel`
- SMS.ir: `sms_ir`

`Database\Seeders\SmsProviderSeeder` creates the four provider types. Melli
Payamak, KaveNegar, and SMS.ir each receive one disabled system account. IPPanel
receives two disabled system accounts: one for normal sending and one for OTP
pattern sending. The seeder is idempotent and stores no real secrets.

Dashboard routes:

- `/admin/sms/providers`
- `/admin/sms/providers/create`
- `/admin/account/sms-settings`
- `/admin/account/sms-settings/test-send`
- `/admin/sms/logs`
- `/admin/sms/statistics`

Users can create private provider accounts and choose an active system provider
or their own active provider as the account default. Normal users cannot create,
edit, or delete system providers. The account settings page includes a
rate-limited test send to the authenticated user's own mobile number.

## Architecture

SMS sending is centralized behind `App\Services\Sms\SmsManager`.

```text
Controller / OTP service
  -> SendSmsAction or OtpSenderInterface
  -> SmsManager
  -> ResolveUserSmsProviderAction
  -> SmsProviderFactory
  -> Provider adapter
  -> Laravel HTTP client
  -> SmsDeliveryLog
```

Resolution rules:

1. Use the user's selected active provider when it is accessible.
2. If fallback is enabled or no setting exists, use an active default system
   provider.
3. OTP purposes prefer a system provider configured with
   `settings.sending_type = pattern`; normal sends avoid pattern-only accounts
   when another active system provider exists.
4. If no default exists, use the active system provider with the lowest
   priority.
5. Throw `SmsProviderNotAvailableException` when no provider is available.

Local and testing environments may use `SMS_FAKE_ENABLED=true`; fake mode still
logs a safe delivery record but does not call an external SMS service.

Provider adapters use Laravel's HTTP client, configured timeouts, normalized
results, and sanitized errors. Controllers never call provider APIs directly.

## IPPanel OTP Payload

The IPPanel OTP system account uses this payload shape:

```json
{
  "sending_type": "pattern",
  "from_number": "{{ippanel_from_number}}",
  "code": "s5jh64gwzlk0jf3",
  "recipients": [
    "+989124248164"
  ],
  "params": {
    "verification-code": "385107"
  }
}
```

IPPanel requests include:

```text
Authorization: YOUR_TOKEN_HERE
Content-Type: application/json
```

## Security

SMS provider credentials are stored on `sms_provider_accounts.credentials` with
Laravel encrypted casts. The encrypted payload is stored in a text column and is
not queryable JSON.

Security rules:

- Never store or display provider credentials in plain text after saving.
- Dashboard views show only masked credential keys.
- Never log credential values or raw provider responses.
- SMS logs store `message_preview` and `message_hash`, not full OTP bodies.
- OTP messages are logged as `OTP message` unless explicit body logging is
  enabled through configuration.
- Users may select active system providers or their own active user providers.
- Users cannot access another user's private provider or credentials.
- Non-super-admin SMS log filters show only system providers and the
  authenticated user's own private providers.
- Only super admins can manage system providers.
- All dashboard write routes use CSRF protection and backend authorization.
- Account test sends are rate-limited and can only target the authenticated
  user's saved mobile number.

Test SMS and OTP request rate limiting should remain enabled on public and admin
authentication flows.

## Adding a Provider

1. Add a new provider type key.
2. Create an adapter that implements `App\Contracts\Sms\SmsProviderInterface`.
3. Register the adapter in `App\Services\Sms\SmsProviderFactory`.
4. Add credential validation in the SMS Form Requests.
5. Add tests with `Http::fake()`.

## Testing

Run the full suite after SMS behavior changes:

```bash
php artisan test
```

Use `Http::fake()` for provider adapter tests. Do not let tests call real SMS
services.

Covered behavior:

- Provider type and disabled system provider seeding.
- Super admin system provider creation.
- User private provider creation.
- Denial for normal users creating system providers.
- Denial for editing or selecting another user's private provider.
- Active provider selection and inactive provider rejection.
- Provider fallback resolution.
- Safe SMS delivery logging.
- Rate-limited account test-send logging.
- SMS dashboard page loading and user-scoped logs.

Set `SMS_FAKE_ENABLED=false` in tests that need to exercise real provider
resolution and adapter code.
