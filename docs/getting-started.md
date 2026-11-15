# Getting started

This takes you from a fresh clone to a running store at `http://kofe.localhost:8000` with an admin login, and a green test run.

## Prerequisites

| Tool | Version | Source of truth |
| --- | --- | --- |
| PHP | 8.3+ with `pdo_mysql`, `gd` or `imagick` (WebP), `intl`, `mbstring`, `bcmath` | [composer.json](../composer.json) `require.php`, CI uses 8.3 ([pest.yml](../.github/workflows/pest.yml)) |
| Composer | 2.x | [composer.lock](../composer.lock) |
| Node.js + npm | current LTS | CI uses `lts/*`; [package-lock.json](../package-lock.json) |
| MySQL (or MariaDB) | 8.x | Required for tenant provisioning: [`TenantService::databaseExists`](../app/Services/TenantService.php) queries `INFORMATION_SCHEMA.SCHEMATA` |
| Redis | any | Recommended cache store (see step 2) |

Why not SQLite for local runs: the automated tests use SQLite (one file per tenant, [tests/TestCase.php](../tests/TestCase.php)), but `app:create-tenant` checks for the tenant database with a MySQL-specific query.

## 1. Install

```bash
git clone git@github.com:habibGamal/turbo_tenant.git
cd turbo_tenant
composer install
npm ci
```

`composer install` runs `package:discover` and `filament:upgrade` through Composer scripts.

## 2. Configure

```bash
cp .env.example .env
php artisan key:generate
```

Change these in `.env` (full list in [Configuration](configuration.md)):

| Setting | Local value | Why |
| --- | --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `mysql` and your local server; e.g. database `turbo_central` | Central database. Tenant databases are created next to it as `tenant<id>`, so the DB user needs `CREATE DATABASE` rights |
| `CACHE_STORE` | `redis` (or `array`) | Stancl's `CacheTenancyBootstrapper` tags every cache call per tenant; the `database` and `file` stores do not support tags |
| `QUEUE_CONNECTION` | `database` | POS delivery, image variants, push notifications and Meta events are queued |
| `DB_QUEUE_CONNECTION` | `mysql` (the central connection name) | Without it, Laravel's `database` queue uses whatever connection is default when the queue is first resolved; inside a tenant request that is the tenant DB, so a central worker would not see those jobs. The POS queue pins the central connection itself ([config/queue.php](../config/queue.php)) |
| `APP_URL` | `http://localhost:8000` | Central URL |

Central domains are hard-coded in [config/tenancy.php](../config/tenancy.php) (`localhost` is one of them). Any other host is treated as a tenant domain.

## 3. Prepare data

```bash
php artisan migrate            # central DB: users, tenants, domains, plugins, tenant_plugins, jobs, cache
php artisan db:seed            # central user admin@example.com (DEFAULT_USER_* in .env) for the /central panel
php artisan plugin:discover    # registers every app/Plugins/*/plugin.json in the central `plugins` table and writes bootstrap/cache/plugins.php
php artisan app:create-tenant --id=kofe --domain=kofe.localhost
```

`app:create-tenant` ([source](../app/Console/Commands/CreateTenant.php)) creates the `tenants` row, which synchronously creates database `tenantkofe`, runs `database/migrations/tenant`, seeds it with [`DatabaseSeeder`](../database/seeders/DatabaseSeeder.php) (one customer account `admin@example.com`), and activates the two core plugins ([`TenancyServiceProvider`](../app/Providers/TenancyServiceProvider.php)). It then creates `storage/tenantkofe/` folders, the domain, and the PWA `manifest.json`.

Activate the commerce plugins, dependencies first (each command runs that plugin's tenant migrations):

```bash
for p in catalog-products locations-delivery cart-engine order-management payment-processing \
         guest-checkout coupons-promotions combo-packages customer-favorites product-reviews \
         hero-banners cms-pages push-notifications pos-integration meta-marketing; do
  php artisan tenant:plugin:activate kofe $p
done
php artisan tenant:plugin:list kofe
```

The seeded account is a customer (`is_admin` defaults to false). Promote it to store admin:

```bash
php artisan tinker --execute="tenancy()->initialize('kofe'); App\Models\User::where('email','admin@example.com')->update(['is_admin'=>true]);"
```

Optional sample menu data: the seeders in [database/seeders](../database/seeders) (`GovernorateSeeder`, `BranchSeeder`, `AreaSeeder`, `CategorySeeder`, `SectionSeeder`, `WeightOptionSeeder`, `ProductSeeder`, `PackageSeeder`) are commented out of `DatabaseSeeder`. Run one inside the tenant with `php artisan tenants:seed --tenants=kofe --class=CategorySeeder`.

## 4. Run

```bash
composer dev
```

This starts `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan pail` (logs) and `npm run dev` (Vite) together ([composer.json](../composer.json)). `php artisan serve:fast` is a variant with OPcache enabled ([source](../app/Console/Commands/ServeFastCommand.php)).

| URL | What you should see |
| --- | --- |
| `http://kofe.localhost:8000` | Storefront home (header, hero area, menu sections) |
| `http://kofe.localhost:8000/admin` | Arabic admin panel; sign in with the promoted account |
| `http://localhost:8000/central` | Central panel with the Tenants list |
| `http://localhost:8000/up` | Laravel health check, HTTP 200 |

Browsers resolve `*.localhost` to 127.0.0.1. Other tools may need a hosts-file entry.

## 5. Verify

```bash
php artisan test --parallel     # Pest, SQLite per process, no MySQL needed
npm run build                   # tsc type check + Vite production build
```

Browser suites (`npm run test:e2e*`) need a provisioned `e2e.localhost` tenant; see [Development guide](development.md#testing).

## Common setup problems

| Symptom | Cause | Fix |
| --- | --- | --- |
| `This cache store does not support tagging` | `CACHE_STORE=database` or `file` | Use `redis` or `array` |
| Tenant domain shows a 404 or the central site | Domain not in `domains` table, or listed in `tenancy.central_domains` | Check `php artisan tinker` → `Stancl\Tenancy\Database\Models\Domain::all()` |
| Storefront API calls return `404 Feature not available` | Plugin not active for the tenant | `php artisan tenant:plugin:activate <tenant> <plugin>` |
| `Cannot activate 'x'. Missing active dependencies: y` | Activation order | Activate `y` first (dependencies are in each `plugin.json`) |
| New plugin folder not recognized | Stale `bootstrap/cache/plugins.php` | `php artisan plugin:discover` (or `plugin:clear`) |
| `/admin` login rejected | User lacks `is_admin` | Promote as in step 3 |
| Images uploaded in admin are not optimized, push/Meta jobs never run | Queue worker not running, or jobs written to the tenant DB's `jobs` table | Keep `composer dev` running and set `DB_QUEUE_CONNECTION` to the central connection |

## Next steps

Read [Architecture](architecture.md), then follow one flow end to end: [Checkout and orders](features/checkout-and-orders.md) is the best starting point.
