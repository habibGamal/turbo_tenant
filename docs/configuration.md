# Configuration

## How configuration is loaded

There are three layers:

1. **Environment variables** (`.env`), read through `config/*.php`. They apply to the whole installation and need `php artisan config:clear` (or `config:cache` in production) plus a worker restart to take effect.
2. **Code-level config** that is not env-driven: central domains in [config/tenancy.php](../config/tenancy.php), timezone (Laravel default `UTC`).
3. **Per-store settings** in each tenant's `settings` table, edited in the admin panel at **الإعدادات والنظام › إعدادات المتجر** (Settings & System › Store settings). They take effect on the next request. Payment gateway, POS, Google, SMTP and Meta credentials live here, not in `.env`.

Per-store **plugin activation** is a fourth switch, stored in the central `tenant_plugins` table ([Plugin marketplace](features/plugin-marketplace.md)).

## Environment variables

Names come from [.env.example](../.env.example) and from `env()` calls in `config/`. Values below are the code defaults.

### Application

| Name | Required | Default | Purpose |
| --- | --- | --- | --- |
| `APP_NAME` | no | `Larament` | Fallback store name when `site_name` is not set |
| `APP_ENV` | yes | `local` | `local` makes `/api/tenant/bootstrap` report `is_dev_mode: true`; `production` blocks E2E seeders |
| `APP_KEY` | yes | — | Encryption and the HMAC key for guest tracking tokens ([GuestTrackingTokenService](../app/Plugins/GuestCheckout/src/Services/GuestTrackingTokenService.php)). Rotating it invalidates existing guest tracking links. Secret |
| `APP_DEBUG` | yes | `true` | Must be `false` in production |
| `APP_URL` | yes | `http://localhost` | Central URL; tenant URLs come from the request host |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | no | `en` | Laravel locale |
| `BCRYPT_ROUNDS` | no | `12` | Password hashing cost |
| `DEFAULT_USER_NAME`, `DEFAULT_USER_EMAIL`, `DEFAULT_USER_PASSWORD` | no | `Admin`, `admin@example.com`, `password` | Account created by `DatabaseSeeder` in central and every new tenant DB. Change before creating production tenants. Secret (password) |
| `DEBUGBAR_ENABLED` | no | `true` in example | Laravel Debugbar; disable in production |

### Database, cache, queue, session

| Name | Required | Default | Purpose |
| --- | --- | --- | --- |
| `DB_CONNECTION` | yes | `sqlite` in example | Central connection name. Use `mysql`: tenant provisioning queries MySQL's `INFORMATION_SCHEMA` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | yes | — | Central DB. The user must be able to `CREATE DATABASE`/`DROP DATABASE` `tenant*`. Secret (password) |
| `CACHE_STORE` | yes | `database` in example | Must support tags (`redis`, `memcached`, `array`) because of `CacheTenancyBootstrapper` |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | if redis | `phpredis`, `127.0.0.1`, `6379` | Redis |
| `QUEUE_CONNECTION` | yes | `database` | Default queue |
| `DB_QUEUE_CONNECTION` | yes (with `database` queue) | unset | Central DB connection for the `database` queue. Unset means the default connection at first use, which can be a tenant DB |
| `POS_QUEUE_DB_CONNECTION` | no | `DB_QUEUE_CONNECTION` → `DB_CONNECTION` | DB connection for the `pos-database` queue ([config/queue.php](../config/queue.php)) |
| `DB_QUEUE_TABLE`, `DB_QUEUE`, `DB_QUEUE_RETRY_AFTER` | no | `jobs`, `default`, `90` | Queue table, name, visibility timeout (must exceed the 60 s POS job timeout) |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN` | no | `database`, `120`, `false`, `/`, `null` | Sessions are stored in the active (tenant) DB. Keep `SESSION_DOMAIN=null` so each store domain gets its own cookie |
| `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_PREFIX` | no | Laravel default | Sanctum |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | no | `file` | `php artisan down` driver |

### Files, mail, logging

| Name | Required | Default | Purpose |
| --- | --- | --- | --- |
| `FILESYSTEM_DISK` | no | `local` | Default disk; tenancy suffixes `local` and `public` per tenant |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` | no | — | S3 disk if used (tenancy only suffixes `local` and `public`). Secret |
| `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | no | `log` | Installation default mail; each store can override with its own SMTP settings |
| `POSTMARK_TOKEN`, `RESEND_KEY`, `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL` | no | — | Optional Laravel service drivers in [config/services.php](../config/services.php). Secret |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_DAILY_DAYS`, `LOG_SLACK_*`, `PAPERTRAIL_URL`, `PAPERTRAIL_PORT`, `LOG_PAPERTRAIL_HANDLER`, `LOG_STDERR_FORMATTER`, `LOG_SYSLOG_FACILITY` | no | `stack`/`single`/`debug` | [config/logging.php](../config/logging.php) |

### Integrations

| Name | Required | Default | Purpose |
| --- | --- | --- | --- |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` | no | — | Installation-level Google OAuth fallback; stores normally set these in Store Settings › Google. Secret |
| `GOOGLE_ALLOWED_CLIENT_IDS` | for native Google login | falls back to `GOOGLE_CLIENT_ID` | Comma-separated client ids accepted in mobile ID tokens ([GoogleIdTokenVerifierService](../app/Services/Auth/GoogleIdTokenVerifierService.php)) |
| `META_GRAPH_VERSION`, `META_HTTP_TIMEOUT` | no | `v26.0`, `10` | Meta Conversions API |
| `NETWORK_ALLOW_PRIVATE_HOSTS` | no | `false` | Allow POS URLs on private/loopback addresses (LAN development only) ([config/network.php](../config/network.php)) |
| `PAYMOB_BASE_URL`, `PAYMOB_SECRET_KEY`, `PAYMOB_PUBLIC_KEY`, `PAYMOB_INTEGRATION_IDS`, `PAYMOB_HMAC_SECRET`, `PAYMOB_CURRENCY`, `PAYMOB_MODE` | no | — | Declared in [config/paymob.php](../config/paymob.php) but not read by the application; Paymob credentials come from each store's settings |
| `VITE_APP_NAME` | no | `${APP_NAME}` | Exposed to the frontend build |
| `E2E_BASE_URL`, `E2E_THEME` | tests only | `http://e2e.localhost:8000`, `default` | Playwright ([playwright.config.ts](../playwright.config.ts)) |

## Store settings (per tenant)

Edited at **إعدادات المتجر** (Store settings). Tabs come from plugins; a tab appears only when its plugin is active.

| Tab (Arabic label) | Keys | Effect | Feature |
| --- | --- | --- | --- |
| المظهر والألوان (Appearance) | `theme`, color/radius customizations | Storefront theme CSS | [Store settings](features/store-settings.md) |
| بطاقات الواجهة (Storefront cards) | `product_show_cards` | Product card display | Store settings |
| الهوية والمتجر (Identity) | `site_name`, `site_name_ar`, `site_description`, `site_logo`, `svg_logo`, `site_favicon`, `image_placeholder`, `contact_*`, `social_*` | Branding, footer, admin brand name | Store settings |
| النظام وساعات العمل (System & hours) | `maintenance_mode`, `work_times`, `accept_orders_after_work_times`, `currency`, Google OAuth keys, SMTP `mail_*` | Ordering hours, currency, sign-in, email | Store settings, [Checkout](features/checkout-and-orders.md) |
| Orders | `tax_rate` (%), `min_order_amount`, `allow_manual_order_status_update` | Pricing and admin status control | [Checkout](features/checkout-and-orders.md#configuration) |
| Payments | `cod_fee`, `online_payments_enabled`, `active_payment_gateway` (`paymob`/`kashier`), `paymob_*`, `kashier_*` | Payment methods and gateway credentials | [Payments](features/payments.md#configuration) |
| نظام نقاط البيع (POS) | `products_repo_link` | Master product repository for import/price sync | [POS](features/pos-integration.md#configuration) |
| Meta | `facebook_app_id` (pixel id), `meta_capi_enabled`, `meta_capi_access_token`, `meta_capi_test_event_code`, `meta_debug_mode`, `meta_catalog_enabled`, `meta_catalog_feed_token`, `meta_catalog_brand` | Pixel, Conversions API, catalog feed | [Meta](features/meta-marketing.md#configuration) |
| (no UI) | `product-reviews.auto_approve` | Publish reviews without moderation | [Reviews](features/product-reviews.md#configuration) |
| (orders list action) | integration token | POS callback auth | [POS](features/pos-integration.md#configuration) |

Settings are stored as plain text. Gateway secrets, the SMTP password, the Meta access token and the integration token are readable by anyone with tenant DB access.

## Secrets

Installation secrets (`APP_KEY`, DB/Redis passwords, AWS keys) belong in the server's environment or a secrets manager, never in the repository. Store-level secrets are entered by store admins in the admin panel. To rotate the POS integration token use the **رمز التكامل** (Integration token) action on the orders list; the old token stops working immediately.

## Per-environment differences

| Setting | Local | Tests ([phpunit.xml](../phpunit.xml)) | Production |
| --- | --- | --- | --- |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `testing` | `production` / `false` |
| `DB_CONNECTION` | `mysql` | `sqlite` `:memory:` + per-process tenant files | `mysql` |
| `CACHE_STORE` | `redis` or `array` | `array` | `redis` |
| `QUEUE_CONNECTION` | `database` | `sync` | `database` (or redis) with workers |
| `MAIL_MAILER` | `log` | `array` | SMTP per store |
| `NETWORK_ALLOW_PRIVATE_HOSTS` | `true` if your POS runs on the LAN | `false` | `false` |
