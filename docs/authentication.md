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

Admin OTP values use `otp_codes.purpose = admin_login`. Dashboard login OTPs
are sent through the enabled system IPPanel SMS gateway and the verification
screen never displays the plain code, including in local debug mode.

Demo users after `php artisan migrate:fresh --seed`:

- Super admin: `09129999999`
- Clinic admin: `09217359491`
- Editor: `09120000001`
- Viewer: `09120000002`

The public visitor OTP flow also sends codes through the system IPPanel gateway
and never includes the plain OTP in API responses.
