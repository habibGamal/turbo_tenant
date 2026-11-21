# Operations and troubleshooting

## Health and monitoring

| Signal | Where | Healthy looks like |
| --- | --- | --- |
| Liveness | `GET /up` on a central domain ([bootstrap/app.php](../bootstrap/app.php)) | HTTP 200. It does not check tenant DBs, cache or workers |
| Store reachability | `GET https://<store>/api/tenant/bootstrap` | 200 with `tenant.id` and `active_plugins` |
| Application log | `storage/logs/laravel.log` (all tenants share it) | No new `ERROR` lines |
| Queue backlog | `SELECT queue, COUNT(*) FROM jobs GROUP BY queue` on the central DB | Near zero between bursts |
| Failed jobs | `php artisan queue:failed` | Empty or known |
| POS delivery | Admin › الطلبات (Orders): failed-orders widget, overdue filter, per-order delivery panel | No orders stuck in `queued`/`sending` > 5 min |
| Payments | `payment_events` table and the log context `gateway`, `merchant_order_id`, `result` | `result` = `success` or `duplicate` |
| Meta | Admin › المحتوى والتسويق › تشخيص تكامل ميتا (Meta diagnostics), `meta_capi_events` | Recent events with success responses |

No error tracker, metrics or alerting is configured in the repository.

## Background work

Setup, supervision and the `DB_QUEUE_CONNECTION` pitfall: [Queue, workers and scheduler](queue-and-workers.md).

| Job / schedule | Trigger | Retries | Safe to re-run? | Failure visible where |
| --- | --- | --- | --- | --- |
| [`SendOrderToPosJob`](../app/Plugins/PosIntegration/src/Jobs/SendOrderToPosJob.php) (`pos-database`) | Order finalized (COD), payment completed, order created/updated with `pos_status=ready`, admin retry | 5 tries, 3 s backoff, 60 s timeout | Only through the admin retry/verification actions | Order view delivery panel and attempts table |
| [`GenerateImageVariantsJob`](../app/Plugins/MediaOptimizer/src/Jobs/GenerateImageVariantsJob.php) | Image upload in admin | 3 | Yes (regenerates files) | `failed_jobs`, missing variants |
| [`SendMetaPurchaseEvent`](../app/Plugins/MetaMarketing/src/Jobs/SendMetaPurchaseEvent.php) | `OrderFinalized`, `OrderPaymentCompleted` | 5 | Meta deduplicates by event id | `meta_capi_events`, Meta diagnostics page |
| [`SendMetaRelayEvent`](../app/Plugins/MetaMarketing/src/Jobs/SendMetaRelayEvent.php) | `POST /api/meta/events` | 3 | Yes | `meta_capi_events` |
| [`SendOrderStatusNotificationJob`](../app/Plugins/PushNotifications/src/Jobs/SendOrderStatusNotificationJob.php) | `OrderCreated`, `OrderStatusUpdated` | default | Sends a duplicate push | log |
| [`SendBulkNotificationJob`](../app/Plugins/PushNotifications/src/Jobs/SendBulkNotificationJob.php) | Campaign "send now", or delayed until `scheduled_at` | default | Re-sends to everyone | Campaign status `failed`, success/failure counts |
| `notifications:send-scheduled` | Scheduler, every minute | — | Yes | Command output |
| [`SetupTenantJob`](../app/Jobs/SetupTenantJob.php) | Tenant created in `/central` | default | Yes (idempotent folder/domain/manifest creation) | Missing `storage/tenant<id>/` |

## Routine tasks

| Task | When | Command / steps |
| --- | --- | --- |
| Clear and rebuild caches | After `.env` or config changes | `php artisan optimize:clear && php artisan optimize && php artisan queue:restart` |
| Refresh plugin manifest cache | After deploying new/changed plugins | `php artisan plugin:discover` |
| Inspect a store's plugins | Support requests | `php artisan tenant:plugin:list <tenant>` |
| Rotate the POS integration token | Suspected leak, POS change | Admin › الطلبات › رمز التكامل (Orders › Integration token) → generate; give the new token to the POS vendor |
| Regenerate image variants | After changing variant sizes or importing images | `php artisan media:migrate-variants` (see `--help` for tenant/scope options) |
| Retry failed generic jobs | After fixing the cause | `php artisan queue:retry <id>` (not for POS deliveries) |
| Back up | Before every release and on a schedule | `mysqldump` central + all `tenant*` DBs; archive `storage/` |

## Runbooks

### A store's domain shows the wrong site or 404

1. **Confirm:** `curl -I https://<domain>/api/tenant/bootstrap`.
2. **Diagnose:** domain missing from `domains` (central DB); domain listed in `tenancy.central_domains`; DNS/TLS pointing elsewhere.
3. **Fix:** add the domain to the tenant in `/central`; remove it from central domains if it was added by mistake (code change + deploy).
4. **Verify:** bootstrap returns that `tenant.id`.

### Product or site images return 404

1. **Confirm:** `curl -sk -o /dev/null -w '%{http_code}\n' https://<domain>/images/<a real path>` (a browser may show a cached copy).
2. **Diagnose:** nginx error log shows `Too many levels of symbolic links` (symlink owner differs from `storage/` while `disable_symlinks if_not_owner` applies); `readlink public/tenant-storage` is not `../storage`; the domain is missing from the vhost's `map $host $tenant_key`.
3. **Fix:** see [Production web server](production-server.md#incident-2026-11-21-every-tenant-image-returned-404): `chown -h` the symlinks to the storage owner, keep `disable_symlinks off;` on the image locations, recreate the symlink, or add the map line; then `nginx -t` and reload.
4. **Verify:** every domain of the store returns `200 image/...` for the same image.

### Storefront section or API returns "Feature not available"

1. **Confirm:** response is `404 {"message":"Feature not available"}`.
2. **Diagnose:** `php artisan tenant:plugin:list <tenant>` shows the plugin not `active`.
3. **Fix:** activate it from the store's **سوق الإضافات** (Plugin marketplace) or `tenant:plugin:activate`. If activation fails with missing dependencies, activate those first.
4. **Verify:** `active_plugins` in bootstrap includes it.

### Customer paid but the order still shows "pending payment"

1. **Confirm:** order `payment_status` and `merchant_order_id`; the gateway dashboard shows the transaction as paid.
2. **Diagnose:** search the log for `Payment webhook` with that `merchant_order_id`: `invalid_signature` (HMAC secret mismatch in Store Settings), `amount_mismatch` (order total changed or currency/cents issue), `order_not_found` (webhook sent to a different store domain), nothing at all (webhook URL wrong in gateway dashboard, app was down, firewall).
3. **Fix:** correct the setting or the dashboard URL, then ask the gateway to resend the callback. Do not edit `payment_status` by hand: that skips `OrderPaymentCompleted`, so POS delivery and Meta purchase events never fire.
4. **Verify:** status `completed`, a `payment_events` row, POS delivery queued.

### POS orders are not reaching the branch

Follow [POS integration › Troubleshooting](features/pos-integration.md#troubleshooting). In short: check a worker is running; check the order's delivery panel (`queued`, `retry_wait`, `needs_verification`, `failed`) and the latest error; for `needs_verification` call the branch before recording received/not received.

### Images uploaded in admin do not appear optimized

Worker not running, or the image extension cannot encode WebP. Check `failed_jobs` for `GenerateImageVariantsJob`, fix, then `php artisan media:migrate-variants`.

### Store says "We are currently closed" during opening hours

Work hours are compared with the server clock in UTC. Enter hours in UTC, or turn on "accept orders after work times" in Store Settings. See [Checkout › Troubleshooting](features/checkout-and-orders.md#troubleshooting).

### Emails (password reset) not delivered for one store

That store's SMTP settings (Store Settings › SMTP) override the installation mail config on every request ([ConfigureTenantMail](../app/Listeners/ConfigureTenantMail.php)). Check host/port/encryption/credentials there; the log has the mailer exception.

## Troubleshooting index

| Symptom | Go to |
| --- | --- |
| Cart total differs from expectation | [Cart](features/cart.md#troubleshooting) |
| Order rejected at checkout | [Checkout and orders](features/checkout-and-orders.md#troubleshooting) |
| Coupon not accepted | [Coupons](features/coupons.md#troubleshooting) |
| Payment stuck or failed | [Payments](features/payments.md#troubleshooting) |
| Guest cannot track order | [Guest checkout](features/guest-checkout.md#troubleshooting) |
| POS delivery problems | [POS integration](features/pos-integration.md#troubleshooting) |
| Push notifications not received | [Push notifications](features/push-notifications.md#troubleshooting) |
| Meta events missing | [Meta marketing](features/meta-marketing.md#troubleshooting) |
| Plugin cannot be activated/deactivated | [Plugin marketplace](features/plugin-marketplace.md#troubleshooting) |
| Store creation failed half-way | [Store provisioning](features/tenant-provisioning.md#troubleshooting) |

## Data management

- **Backups:** not configured in the repository. Back up the central DB, every `tenant*` DB and `storage/` together; a tenant restore must match the central `tenants`/`domains`/`tenant_plugins` rows.
- **Deleting a store** deletes its database synchronously (`TenantDeleted` → `DeleteDatabase` in [TenancyServiceProvider](../app/Providers/TenancyServiceProvider.php)). `storage/tenant<id>/` is not removed automatically.
- **Retention:** no cleanup jobs exist for old carts, sessions, `payment_events`, `meta_capi_events` or POS attempt history; they grow with traffic.
- **Personal data:** customer names, phones, emails and address snapshots are stored in orders; guest identities in `guest_users`.
