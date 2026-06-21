# Architecture: WHM/cPanel

## Goal

The MVP should be deployable to a WHM/cPanel server with root access while still
keeping the application portable for future container or cloud deployment.

## Recommended Production Shape

```text
Nginx/Apache vhost
  -> Laravel public/index.php
  -> Laravel app
  -> MySQL/MariaDB
  -> Redis
  -> Horizon worker process
  -> storage/app/private
```

## Domain Routing

Each tenant domain or subdomain must point to the same Laravel app. Laravel
resolves the tenant from the incoming host through `tenant_domains.domain`.

Do not pass tenant identity through query strings, form fields, headers from the
browser, or request bodies.

## Storage

- Public webroot is `public/`.
- Uploaded visitor photos live under `storage/app/private`.
- Generated assets live under `storage/app/private/tenants/...`.
- Assets are streamed through scoped API routes.
- Do not create public symlinks for visitor uploads or generated assets.

## Queue

Production queue-heavy work must use:

```env
QUEUE_CONNECTION=redis
```

Run Horizon as a managed process through Supervisor, systemd, or the hosting
panel process manager.

## Deployment Checklist

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
