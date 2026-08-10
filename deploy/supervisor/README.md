# Supervisor Setup

This directory contains a production-ready Supervisor config for queue workers and long-lived access-control monitors.

## Files

- access-control.conf: generated config that always includes queue + MQTT monitor, and conditionally includes serial/OPC/Modbus programs from DB state.

## Generate / Rebuild

```bash
php artisan app:rebuild-access-control-supervisor
```

By default this also runs Supervisor apply steps (`supervisorctl reread` + `supervisorctl update`).

Skip apply steps:

```bash
php artisan app:rebuild-access-control-supervisor --skip-supervisorctl
```

Force apply steps:

```bash
php artisan app:rebuild-access-control-supervisor --apply-supervisorctl
```

Fail the command if apply steps fail:

```bash
php artisan app:rebuild-access-control-supervisor --strict-supervisorctl
```

Dry run:

```bash
php artisan app:rebuild-access-control-supervisor --dry-run
```

Optional output path:

```bash
php artisan app:rebuild-access-control-supervisor --path=/tmp/access-control.conf
```

The application also auto-rebuilds this file when:

- access readers are created/updated/deleted/restored (identifier or serial config changes)
- access sources are created/updated/deleted/restored (type or enabled changes)

Enable automatic supervisorctl apply after those rebuilds with:

- `ACCESS_CONTROL_SUPERVISOR_AUTO_APPLY=true`

## Install

```bash
sudo cp /var/www/html/deploy/supervisor/access-control.conf /etc/supervisor/conf.d/access-control.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Useful commands

```bash
sudo supervisorctl restart access-control-queue:*
sudo supervisorctl restart access-control-mqtt-monitor
sudo supervisorctl tail -f access-control-queue:access-control-queue_00
sudo supervisorctl tail -f access-control-mqtt-monitor
```

## Runtime expectations

- QUEUE_CONNECTION should be set to redis.
- redis-server must be running and reachable on REDIS_HOST/REDIS_PORT.
- app:monitor-reader-push should run as a dedicated long-lived process.
- app:monitor-serial-reader should run as one dedicated process per serial reader.
- the Supervisor user for serial readers must have access to `/dev/ttyUSB*`; for the current setup this means `www-data` should belong to the `dialout` group.

## Serial reader program example

Generated as one Supervisor program per serial reader identifier:

```ini
[program:access-control-serial-ttyUSB2]
command=/usr/bin/php /var/www/html/artisan app:monitor-serial-reader ttyUSB2 --device=/dev/ttyUSB2 --baud=9600 --timeout=1
autostart=true
autorestart=true
startsecs=2
startretries=10
user=www-data
directory=/var/www/html
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/supervisor-serial-ttyUSB2.log
stopasgroup=true
killasgroup=true
stopwaitsecs=30
```

## Health check command

Run the application-level health check after deploys and during troubleshooting:

```bash
php artisan app:health-access-control
```

For machine-readable output (for scripts/alerts):

```bash
php artisan app:health-access-control --json
```

This verifies:

- queue driver configuration
- Redis ping and queue depth
- failed job count
- Supervisor program status for queue workers + monitor
- process presence via pgrep
