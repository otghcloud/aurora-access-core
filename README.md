# Aurora Access Core

Aurora Access Core is a Laravel package that provides the access-control domain, API routes, admin UI, migrations, commands, and runtime services.

This README is intentionally consumer-focused.

## Installation

```bash
composer require otghcloud/aurora-access-core
```

Optional adapter packages:

```bash
composer require \
  otghcloud/aurora-access-adapter-edgelink \
  otghcloud/aurora-access-adapter-modbus \
  otghcloud/aurora-access-adapter-opc \
  otghcloud/aurora-access-adapter-serial-wiegand
```

## Integration Checklist

1. Run package migrations:

```bash
php artisan migrate
```

2. Ensure your environment includes database, Redis, and MQTT values required by your deployment.

3. Create and authorize administrative users in your host app.

## What gets registered

- Routes:
  - Web/admin routes from `routes/web.php`
  - API routes under `/api` from `routes/api.php`
- Console:
  - Commands and schedule registrations from `routes/console.php`
- Migrations:
  - Core access-domain migration set
- Config:
  - `mqtt-client.php` defaults
- Views:
  - Package Blade templates used by the admin/auth UI

## Publishing assets

```bash
php artisan vendor:publish --tag=aurora-access-core-config
php artisan vendor:publish --tag=aurora-access-core-migrations
php artisan vendor:publish --tag=aurora-access-core-views
```
