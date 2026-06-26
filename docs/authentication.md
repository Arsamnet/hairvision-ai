# Authentication

HairVision has two separate OTP flows.

## Public Visitor OTP

Visitor OTP endpoints remain under `/api/v1/auth/otp/*`. They verify a phone
number for the current host-resolved tenant and store a verified `lead_id` in
the visitor session.

Controls:

- OTP values are stored only as hashes.
- OTP records are tenant-scoped.
- OTP records expire.
- OTP records are single-use.
- Failed attempts are counted and exhausted records are invalidated.
- Request throttling uses Laravel's rate limiter.

## Admin Dashboard OTP

Dashboard users authenticate with mobile OTP under `/admin`.

Routes:

- `GET /admin`
- `POST /admin/otp/request`
- `GET /admin/otp`
- `POST /admin/otp/verify`
- `POST /admin/logout`

The admin flow uses Laravel session authentication. The public verified-lead
session and authenticated dashboard user session are intentionally separate.

Admin OTP values use `otp_codes.purpose = admin_login`. Admin OTP delivery goes
through `App\Services\Otp\OtpSenderInterface`, which is bound to the centralized
SMS manager. Public visitor OTP delivery uses the SMS manager system-provider
path. Local and testing environments may use fake SMS mode, which logs a safe
delivery attempt without calling an external provider.

Demo users after `php artisan migrate:fresh --seed`:

- Super admin: `09129999999`
- Clinic admin: `09120000000`
- Editor: `09120000001`
- Viewer: `09120000002`

In local debug mode, the verification screen displays a temporary test code.
