# Tech Stack Requirements

## Runtime

- PHP 8.3+
- Laravel 13
- Composer
- Node.js LTS
- Vite
- Tailwind CSS

## Laravel Packages

Required for version `1.0.0`:

- `laravel/framework`
- `laravel/sanctum`
- `laravel/horizon`
- `predis/predis`

## Infrastructure

Development and production should use:

- Redis for cache, sessions, and queues.
- Laravel Horizon for queue monitoring.
- Private local storage for MVP uploads and generated assets.
- MySQL/MariaDB in production or staging.
- SQLite is acceptable for local automated tests.

## Frontend

- Blade is the only MVP frontend.
- Public UI is Persian and right-to-left.
- Font is embedded Vazirmatn from `resources/fonts/vazirmatn`.
- No CDN is allowed for CSS, JavaScript, fonts, or icons.
- Tenant logos and theme images may be served from the primary HairVision domain.

## Configuration Baseline

`.env.example` must stay aligned with MVP architecture:

```env
APP_NAME=HairVision
APP_VERSION=1.0.0
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis
HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
```

Tests may override queues and sessions to `sync` and `array` through `phpunit.xml`
for deterministic execution.
