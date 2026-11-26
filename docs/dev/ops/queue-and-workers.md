# Queue, workers and scheduler

Several features only work when a queue worker and the scheduler are running. This page explains what runs in the background, how to configure it, and the one setting that silently breaks it.

## What runs in the background

| Work | Job | Connection | Without a worker |
| --- | --- | --- | --- |
| Send orders to the branch POS | [`SendOrderToPosJob`](../../../app/Plugins/PosIntegration/src/Jobs/SendOrderToPosJob.php) | `pos-database` | Orders stay `queued` («في قائمة الإرسال») and never reach the kitchen |
| Image variants (WebP) | [`GenerateImageVariantsJob`](../../../app/Plugins/MediaOptimizer/src/Jobs/GenerateImageVariantsJob.php) | `database` | Images are served unoptimized |
| Meta Conversions API | [`SendMetaPurchaseEvent`](../../../app/Plugins/MetaMarketing/src/Jobs/SendMetaPurchaseEvent.php), [`SendMetaRelayEvent`](../../../app/Plugins/MetaMarketing/src/Jobs/SendMetaRelayEvent.php) | `database` | No server-side purchases in Events Manager |
| Push notifications | [`SendOrderStatusNotificationJob`](../../../app/Plugins/PushNotifications/src/Jobs/SendOrderStatusNotificationJob.php), [`SendBulkNotificationJob`](../../../app/Plugins/PushNotifications/src/Jobs/SendBulkNotificationJob.php) | `database` | No order or campaign pushes |
| Store setup after creation | [`SetupTenantJob`](../../../app/Jobs/SetupTenantJob.php) | `database` | Store folders / manifest missing |
| Scheduled campaigns | `notifications:send-scheduled` every minute ([routes/console.php](../../../routes/console.php)) | scheduler | Scheduled campaigns never go out |

All jobs are written to **one `jobs` table in the central database**, even though they are dispatched inside a tenant request. Each job re-initializes its tenant when it runs.

## The `DB_QUEUE_CONNECTION` pitfall

Laravel's `database` queue uses the connection named in `DB_QUEUE_CONNECTION`. If it is **unset**, Laravel uses whatever connection is the default when the queue is first resolved. Inside a tenant request that is the **tenant** database, so jobs land in `tenant123.jobs`, where the central worker never looks. Nothing errors; the work simply never happens.

```dotenv
QUEUE_CONNECTION=database
DB_CONNECTION=mysql
DB_QUEUE_CONNECTION=mysql        # the CENTRAL connection name, always set it
DB_QUEUE_RETRY_AFTER=90          # must be longer than the 60 s POS job timeout
# POS_QUEUE_DB_CONNECTION=mysql  # optional; defaults to DB_QUEUE_CONNECTION
```

The `pos-database` connection ([config/queue.php](../../../config/queue.php)) pins the central connection on its own, so POS deliveries work even when `DB_QUEUE_CONNECTION` is missing. The other jobs do not.

Delayed POS retries over 24 hours (`1h, 2h, 4h, 8h, 9h` in [`PosRetrySchedule`](../../../app/Plugins/PosIntegration/src/Support/PosRetrySchedule.php)) rely solely on the queue worker: when a round finishes unreachable, [`SendOrderToPosJob`](../../../app/Plugins/PosIntegration/src/Jobs/SendOrderToPosJob.php) dispatches a new delayed job via `pos-database` / `database`, which stores the future execution timestamp in the central `jobs.available_at` column. No Laravel scheduler (`schedule:run`) entry is needed or changed for delayed POS retries.

**How to spot it:** `SELECT queue, COUNT(*) FROM jobs GROUP BY queue;` on the central DB stays empty while images stay unoptimized; a tenant database has rows in its own `jobs` table.

**How to fix:** set `DB_QUEUE_CONNECTION`, run `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`. Jobs already stuck in tenant `jobs` tables are not moved; re-trigger them (re-upload images, use the order's «إرسال لنقطة البيع» action).

## Running the worker

One worker on the `database` connection processes both `database` and `pos-database` jobs, because both use the same central `jobs` table and the `default` queue.

```bash
php artisan queue:work database --queue=default --tries=1 --timeout=90 --sleep=3
```

`--tries=1` is safe: each job class sets its own `$tries` (POS 5, Meta 5/3, images 3), which wins over the CLI value.

### Supervisor

```ini
; /etc/supervisor/conf.d/turbo-tenant-worker.conf
[program:turbo-tenant-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/turbo_tenant/current/artisan queue:work database --queue=default --tries=1 --timeout=90 --sleep=3 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/www/turbo_tenant/shared/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start "turbo-tenant-worker:*"
```

### systemd (alternative)

```ini
# /etc/systemd/system/turbo-tenant-worker.service
[Unit]
Description=Turbo Tenant queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
RestartSec=3
WorkingDirectory=/var/www/turbo_tenant/current
ExecStart=/usr/bin/php artisan queue:work database --queue=default --tries=1 --timeout=90 --sleep=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

## Scheduler

One cron entry on one server:

```bash
* * * * * cd /var/www/turbo_tenant/current && php artisan schedule:run >> /dev/null 2>&1
```

## After every deploy: `queue:restart`

Workers keep the old code in memory. After a deploy (and after any `.env` change) run:

```bash
php artisan optimize
php artisan queue:restart
```

`queue:restart` asks each worker to exit after its current job; Supervisor/systemd starts a fresh one with the new code. It needs a working cache store, because the restart signal is stored in the cache.

## Health checks

| Check | Command |
| --- | --- |
| Backlog | `SELECT queue, COUNT(*) FROM jobs GROUP BY queue;` (central DB) |
| Failed jobs | `php artisan queue:failed` |
| Workers alive | `supervisorctl status` / `systemctl status turbo-tenant-worker` |
| POS deliveries stuck | Admin › الطلبات: «فشل الإرسال لنقطة البيع» tab and the overdue filter |

Do not use `queue:retry` for POS deliveries: a retried old job finds its delivery generation stale and does nothing, by design. Use the order's resend action instead ([POS integration](../features/pos-integration.md#troubleshooting)).

Related: [Deployment](deployment.md), [Operations](operations.md), [Configuration](../configuration.md).
