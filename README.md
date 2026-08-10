# Access Control Platform

Laravel-based access control platform for door readers, lock outputs, event logging, and health diagnostics.

The system supports multiple hardware integration paths (serial readers, MQTT, Modbus, OPC UA) and includes both an admin UI and token-protected API endpoints.

## What This Project Does

- Validates card and keypad credentials against configured readers.
- Emits access events and dispatches lock actions asynchronously.
- Monitors serial-connected readers directly in Laravel (no external Python listener required).
- Publishes and reconciles MQTT reader state.
- Monitors Modbus and OPC UA sources and dispatches input actions.
- Provides operational diagnostics in `/admin/health/*`.

## Key Features

- Laravel-native serial reader monitor (`app:monitor-serial-reader`) with:
	- card/fob reads
	- keypad buffering and timeout flush
	- duplicate swipe suppression
	- doorbell key handling
- Access event pipeline backed by queues.
- Reader control API secured with Sanctum tokens.
- Admin CRUD for readers, locks, sources, bindings, users, cards, areas, and permissions.
- Health pages for overall platform, serial devices, Modbus diagnostics, and OPC diagnostics.
- Supervisor-ready process layout for production.

## Tech Stack

- PHP 8.3+
- Laravel 13
- Redis queues
- MariaDB/MySQL
- MQTT (`php-mqtt/laravel-client`)
- OPC UA (`techdock/opcua`)
- Frontend tooling: Vite, Bootstrap, Tailwind, Livewire
- Testing: Pest + PHPUnit

## Architecture Overview

Credential flow:

1. Reader input arrives via serial monitor or external caller.
2. Request is handled by shared access logic.
3. Access event is persisted.
4. Jobs are dispatched for lock actuation and reader feedback/state publishing.

Main endpoints and surfaces:

- Public credential ingest:
	- `POST /validate`
	- `POST /doorbell`
- API (Sanctum):
	- `POST /api/auth/token`
	- `DELETE /api/auth/token`
	- `GET /api/readers`
	- `GET /api/readers/{identifier}`
	- `POST /api/readers/{identifier}/lock`
	- `POST /api/readers/{identifier}/unlock`
	- `PUT /api/readers/{identifier}/autolock`
- Admin health pages:
	- `/admin/health/overview`
	- `/admin/health/serial-devices`
	- `/admin/health/modbus-diagnostics`
	- `/admin/health/opc-diagnostics`

## Requirements

- Linux host (recommended for serial devices and Supervisor setup)
- PHP extensions typically required by Laravel + your DB/Redis drivers
- Redis running and reachable
- MariaDB/MySQL running and reachable
- Node.js + npm (for frontend assets)
- Supervisor (production process management)

For serial readers on Debian/Ubuntu:

```bash
sudo usermod -a -G dialout www-data
```

After group membership changes, restart affected long-lived services (php-fpm/apache/supervisor-managed processes) so they pick up supplementary groups.

## Quick Start (Local)

1. Install dependencies and bootstrap app:

```bash
composer setup
```

This runs install, `.env` creation, key generation, migrations, npm install, and frontend build.

2. Start dev services:

```bash
composer dev
```

This runs:

- `php artisan serve`
- `php artisan queue:listen`
- `php artisan pail`
- `npm run dev`

3. Open app:

- Web/API base: `http://127.0.0.1:8000` (or your configured host)

## Environment Configuration

Start from `.env.example` and review at minimum:

- App:
	- `APP_ENV`, `APP_DEBUG`, `APP_URL`
- Database:
	- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Queue/Redis:
	- `QUEUE_CONNECTION=redis`
	- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- MQTT:
	- `MQTT_HOST`, `MQTT_PORT`, `MQTT_PROTOCOL`
	- `MQTT_MONITOR_CONNECTION`, `MQTT_CLIENT_ID`, optional TLS/auth values
- Access-control tuning (`config/access-control.php`):
	- MQTT topic base/suffix values
	- dedupe timing
	- Modbus poll/timeout defaults
	- OPC monitor max runtime

## Serial Reader Monitor

The platform can monitor serial-connected readers directly in Laravel.

Run one reader manually:

```bash
php artisan app:monitor-serial-reader ttyUSB2
```

Optional runtime overrides:

```bash
php artisan app:monitor-serial-reader ttyUSB2 --device=/dev/ttyUSB2 --baud=9600 --timeout=1
```

Serial monitor behavior:

- card/fob reads
- keypad digit buffering and timeout submission
- duplicate suppression
- `*` suppression
- `#` as doorbell signal

Reader serial config keys (`access_readers.config.serial`):

- `device`
- `baud_rate`
- `timeout`
- `duplicate_window`
- `doorbell_duplicate_window`
- `keypad_timeout`
- `card_min_value`
- `doorbell_value`

Default device resolution:

1. `config.serial.device`
2. fallback `/dev/{reader_identifier}`

## Production Process Management (Supervisor)

Reference config: `deploy/supervisor/access-control.conf`

Includes:

- queue workers
- MQTT monitor
- Modbus monitor (only when enabled Modbus sources exist)
- OPC monitor queue workers (only when enabled OPC sources exist)
- serial monitor processes for configured serial readers

Rebuild the config from current DB state:

```bash
php artisan app:rebuild-access-control-supervisor
```

Preview without writing:

```bash
php artisan app:rebuild-access-control-supervisor --dry-run
```

The app also auto-rebuilds this file when relevant reader/source records change.
Config keys:

- `access-control.supervisor.config_path`
- `access-control.supervisor.auto_rebuild`

Install/update:

```bash
sudo ln -sfn /var/www/html/deploy/supervisor/access-control.conf /etc/supervisor/conf.d/access-control.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Health and Diagnostics

CLI health check:

```bash
php artisan app:health-access-control
php artisan app:health-access-control --json
```

What it validates:

- queue + Redis availability
- failed jobs status
- supervisor/process presence
- serial reader process and device readability checks
- MQTT state probe and drift sync status

Admin diagnostics:

- Health Overview: `/admin/health/overview`
- Serial Devices: `/admin/health/serial-devices`
- Modbus Diagnostics: `/admin/health/modbus-diagnostics`
- OPC Diagnostics: `/admin/health/opc-diagnostics`

## API Authentication (Sanctum)

Create token:

```bash
curl -X POST http://127.0.0.1/api/auth/token \
	-H "Content-Type: application/json" \
	-d '{
		"email": "admin@example.com",
		"password": "your-password",
		"device_name": "home-assistant"
	}'
```

Use `Authorization: Bearer <token>` for protected routes.

Revoke current token:

```bash
curl -X DELETE http://127.0.0.1/api/auth/token \
	-H "Authorization: Bearer <token>"
```

## Reader Control API Examples

Unlock reader:

```bash
curl -X POST http://127.0.0.1/api/readers/ttyUSB1/unlock \
	-H "Authorization: Bearer <token>" \
	-H "Content-Type: application/json" \
	-d '{
		"allow_auto_relock": true,
		"reason": "Remote release"
	}'
```

Update autolock:

```bash
curl -X PUT http://127.0.0.1/api/readers/ttyUSB1/autolock \
	-H "Authorization: Bearer <token>" \
	-H "Content-Type: application/json" \
	-d '{
		"enabled": true,
		"duration": 15,
		"sync_device_tag": true
	}'
```

## Useful Artisan Commands

- `php artisan app:monitor-serial-reader {reader}`
- `php artisan app:monitor-reader-push --connection=monitor --qos=0`
- `php artisan app:monitor-modbus-sources`
- `php artisan app:sync-reader-mqtt-state --json`
- `php artisan app:reconcile-reader-lock-state`
- `php artisan app:health-access-control --json`
- `php artisan app:modbus-input-diagnostics --json`
- `php artisan app:opc-input-diagnostics --json`

## Testing and Quality

Run all tests:

```bash
composer test
```

Run formatter:

```bash
composer format
```

Run targeted tests (example):

```bash
php artisan test tests/Feature/Admin/SerialDevicesHealthPagesTest.php
```

## Troubleshooting

### Serial device reports not readable

- Confirm process user can read device:

```bash
sudo -u www-data php -r 'var_export(is_readable("/dev/ttyUSB2")); echo PHP_EOL;'
```

- Ensure `www-data` is in `dialout`:

```bash
id www-data
```

- Restart long-lived workers after group changes (php-fpm/apache/supervisor).

### Serial page shows monitor running but supervisor unknown

- Verify `supervisorctl status` from runtime context.
- Verify `sudo -n supervisorctl status` is available if permission-gated.

### Reader process in BACKOFF

- Inspect logs:

```bash
sudo tail -n 200 /var/www/html/storage/logs/supervisor-serial-ttyUSB0.log
sudo tail -n 200 /var/www/html/storage/logs/supervisor-serial-ttyUSB2.log
```

### Queue-backed actions delayed

- Confirm workers:

```bash
sudo supervisorctl status access-control-queue:*
```

- Check failed jobs and health output.

## Notes

- The serial reader workflow is now Laravel-native and no Python listener is required for core reader ingest.
- Keep one serial monitor process per physical reader for predictable keypad buffer and dedupe behavior.
