# Aurora Access Core

Aurora Access Core is a Laravel package that provides the access-control domain, API routes, admin UI, migrations, commands, and runtime services.

This README is intentionally consumer-focused.

## Quick Setup (One Command)

Download and run the installer:

```bash
curl -fsSL https://raw.githubusercontent.com/otghcloud/aurora-access-core/main/setup.sh | bash
```

The setup script will:
- Prompt for install directory
- Validate required tooling (PHP, Composer, npm) and PHP extensions
- Prompt for database, Redis, and MQTT settings
- Prompt for initial admin user details (name, email, password)
- Create a fresh Laravel app and install Aurora Access Core
- Run migrations and frontend build

Security note:
- Review remote scripts before running in sensitive environments.

## Setup Script Options

```bash
bash setup.sh --help
```

Supported options:
- `--target <directory>`: target directory for the Laravel app
- `--core-version <constraint>`: package constraint for `otghcloud/aurora-access-core`
- `--with-adapters`: install adapter packages during setup
- `--use-local-packages <dir>`: use local package paths from `<dir>/packages/*` (development/smoke use)
- `--validate-only`: verify installer prerequisites and exit without creating an app
- `--non-interactive`: skip prompts and use `DEFAULT_*` environment variables

Non-interactive example:

```bash
DEFAULT_TARGET_DIR=./aurora-access \
DEFAULT_APP_NAME="Aurora Access Control" \
DEFAULT_APP_URL="http://localhost" \
DEFAULT_DB_CONNECTION=sqlite \
DEFAULT_DB_DATABASE=database/database.sqlite \
DEFAULT_REDIS_HOST=127.0.0.1 \
DEFAULT_REDIS_PORT=6379 \
DEFAULT_MQTT_HOST=127.0.0.1 \
DEFAULT_MQTT_PORT=1883 \
DEFAULT_ADMIN_NAME="Admin User" \
DEFAULT_ADMIN_EMAIL="admin@example.com" \
DEFAULT_ADMIN_PASSWORD="change-me-123" \
bash setup.sh --non-interactive
```

## Manual Installation

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

To create the initial login user directly via artisan:

```bash
php artisan app:create-initial-admin-user --name="Admin User" --email="admin@example.com" --password="change-me-123"
```

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
- Frontend assets:
  - Prebuilt package CSS and JavaScript published to `public/vendor/aurora-access-core/build`

## Publishing assets

```bash
php artisan vendor:publish --tag=aurora-access-core-config
php artisan vendor:publish --tag=aurora-access-core-migrations
php artisan vendor:publish --tag=aurora-access-core-views
php artisan vendor:publish --tag=aurora-access-core-assets
php artisan optimize:clear
```

Aurora Access Core ships its compiled frontend assets with the Composer package. Consumer applications publish those assets; they do not compile the package source through their own Vite build.
