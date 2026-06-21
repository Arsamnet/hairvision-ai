# Setup

This guide covers local development setup and the production deployment shape for
HairVision `1.0.0`.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js LTS
- MySQL/MariaDB or SQLite for local testing
- Redis
- Git
- VSCode, Codex IDE extension, or Codex CLI when using AI-assisted development

## Local Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Map the test tenant host:

```text
127.0.0.1 clinic1.hairvision-ai.test
```

Run the app:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

Open:

```text
http://clinic1.hairvision-ai.test:8000
```

## Configuration Baseline

`.env.example` must stay aligned with the MVP architecture:

```env
APP_NAME=HairVision
APP_VERSION=1.0.0
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis
HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
```

Tests may override queues and sessions to `sync` and `array` through
`phpunit.xml` for deterministic execution.

## Useful Commands

```bash
php artisan migrate:fresh --seed
php artisan route:list --path=api/v1
php artisan test
php artisan horizon
php artisan queue:failed
php artisan optimize:clear
npm run build
```

## Production Deployment Checklist

1. Install PHP extensions required by Laravel and image validation.
2. Configure database credentials.
3. Configure Redis.
4. Set `APP_ENV=production` and `APP_DEBUG=false`.
5. Set `APP_KEY`.
6. Run `composer install --no-dev --optimize-autoloader`.
7. Run `npm run build`.
8. Run migrations.
9. Start Horizon.
10. Run smoke tests against the test tenant host.
