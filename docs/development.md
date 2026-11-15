# Development guide

## Repository layout

| Path | Contents |
| --- | --- |
| [`app/Core/Plugins`](../app/Core/Plugins) | Plugin kernel (registry, lifecycle, contributions, routing, middleware) |
| [`app/Plugins/<Name>`](../app/Plugins) | One folder per feature: `plugin.json`, `<Name>ServiceProvider.php`, optional `<Name>Plugin.php`, `routes/tenant.php`, `src/`, `database/migrations/tenant/` |
| [`app/Http`](../app/Http), [`app/Providers`](../app/Providers), [`app/Filament`](../app/Filament), [`app/Models`](../app/Models) | Host code shared by all plugins |
| [`routes/`](../routes) | `tenant.php` (storefront shell, auth, bootstrap), `auth.php`, `web.php` (central), `console.php` (schedule) |
| [`resources/js`](../resources/js) | React SPA: `core/`, `plugins/<id>/`, `themes/default/`, `locales/{en,ar}` |
| [`resources/views/app.blade.php`](../resources/views/app.blade.php) | SPA shell |
| [`database/`](../database) | Central migrations, `migrations/tenant`, seeders, factories |
| [`tests/`](../tests) | `Unit`, `Feature` (incl. `Security`, `Architecture`), `Browser` (Pest browser), `e2e` (Playwright), `performance` |
| [`docs/`](README.md) | This handbook; `engineering-atlas/` is generated |
| `.agents/skills/` | Agent skills, including the documentation skill used for this handbook |

Do not hand-edit: `bootstrap/cache/*.php` (plugin and framework caches), `public/build`, `docs/engineering-atlas/*` except `definition.json`, `vendor/`, `node_modules/`.

## Day-to-day commands

| Task | Command | Notes |
| --- | --- | --- |
| Run everything locally | `composer dev` | server, queue listener, log tail, Vite |
| All PHP tests | `php artisan test --parallel` or `composer pest` | SQLite, no external services |
| One test | `php artisan test --filter=CartEngineApiTest` | |
| Security tests only | `php artisan test --group=security` | `tests/Feature/Security` |
| Full quality gate | `composer review` | Pint, Rector, PHPStan, Pest |
| Code style | `composer pint` | CI also runs Pint on push |
| Static analysis | `composer phpstan` | [phpstan.neon](../phpstan.neon) |
| Type check + build SPA | `npm run build` | `tsc && vite build` |
| Browser tests | `npm run test:e2e` / `npm run test:e2e:auth` / `npm run test:e2e:ui` | Need a provisioned `e2e.localhost` tenant |
| New tenant migration for a plugin | add a file in `app/Plugins/<Name>/database/migrations/tenant`, then `php artisan tenants:plugin:migrate --plugin=<id>` | |
| Refresh plugin list | `php artisan plugin:discover` | After adding/renaming a plugin or changing `plugin.json` |

## Conventions

Evidenced by configuration, tests and the existing code:

- `declare(strict_types=1)` and `final` classes; Pint formatting ([pint.json](../pint.json)); PHPStan via Larastan; Rector ([rector.php](../rector.php)).
- `env()` only in `config/` (architecture test in [tests/Feature/ArchTest.php](../tests/Feature/ArchTest.php)); no `dd`/`dump`.
- **Plugin boundaries** ([PluginBoundariesArchTest](../tests/Feature/Architecture/PluginBoundariesArchTest.php)): controllers stay thin with validation in FormRequests; a plugin may use only host code and the plugins listed in its manifest `dependencies`; cross-plugin collaboration goes through published interfaces, never by writing another plugin's tables. Existing exceptions are in [boundary-allowlist.php](../tests/Feature/Architecture/boundary-allowlist.php), which may only shrink.
- Plugin namespaces are `App\Plugins\<Name>\src\...` (lower-case `src`).
- Admin UI text is Arabic; storefront strings go through i18next keys in `resources/js/locales/{en,ar}`.
- Migration stubs omit `down()`.
- Commit messages use Conventional Commits with a scope (`feat(storefront): ...`, `fix(pos): ...`).

## Adding a feature

New business capabilities are plugins. Use [Coupons](features/coupons.md) or [Guest checkout](features/guest-checkout.md) as worked examples: both extend checkout without the order plugin knowing about them.

1. **Scaffold:** `php artisan make:plugin loyalty-points --name="Loyalty Points" --category=marketing --filament --migrations --frontend --deps=order-management` ([MakePluginCommand](../app/Console/Commands/MakePluginCommand.php)), then `php artisan plugin:discover`.
2. **Manifest** (`plugin.json`): `id`, `dependencies`, `providers`, optional `plugin` (class implementing [`TenantPluginInterface`](../app/Core/Plugins/Contracts/TenantPluginInterface.php), usually extending [`AbstractTenantPlugin`](../app/Core/Plugins/AbstractTenantPlugin.php)), `filament` (Filament plugin class), `listeners` (event → listener classes, automatically gated by activation), `tenant_scoped` (singletons reset per tenant).
3. **Data:** migrations in `database/migrations/tenant`; models in `src/Models`. They run on activation.
4. **HTTP:** `routes/tenant.php` using `PluginRoutes::tenant('<id>')` so the route is gated; FormRequests for validation; `ApiResponse` for responses.
5. **Hook into other features** through tags (`order.placement.rules`, `store-settings.sections`, `tenant.bootstrap.contributors` ... see [Architecture › Extension points](architecture.md#extension-points)) and, for checkout, by registering pipes in `bootForTenant()`:
   ```php
   app(OrderPipelineRegistrar::class)->registerPricingPipe(ApplyLoyaltyDiscountPipe::class, 250);
   ```
   React to orders through `listeners` on `OrderCreated`, `OrderFinalized`, `OrderPaymentCompleted`, `OrderPaymentFailed`, `OrderStatusUpdated`.
6. **Admin:** Filament resources inside the plugin's Filament plugin class; settings as a `SettingsSection` tagged `store-settings.sections`.
7. **Storefront:** `resources/js/plugins/<id>/` with API hooks and default components; register slots in its `index.ts` and add the call to [plugins/registry.ts](../resources/js/plugins/registry.ts); guard routes with `<RequirePlugin id="<id>">` in [AppRouter.tsx](../resources/js/core/router/AppRouter.tsx).
8. **Tests:** feature tests under `tests/Feature/<Plugin>` (the base [TestCase](../tests/TestCase.php) creates and initializes a tenant), unit tests under `tests/Unit/<Plugin>`, a Playwright spec under `tests/e2e/plugins/` if it has UI. Run the architecture test.
9. **Docs:** add `docs/features/<slug>.md`, a catalog row, config entries and an `Unreleased` changelog entry; run `python .agents/skills/engineering-atlas/scripts/docs_check.py .`.

## Testing

| Suite | Location | Command | Needs | In CI |
| --- | --- | --- | --- | --- |
| Unit | [tests/Unit](../tests/Unit) | `php artisan test --testsuite=Unit` | nothing | yes |
| Feature / API / Filament | [tests/Feature](../tests/Feature) | `php artisan test` | SQLite (tenant DB file per process in `database/testing/`) | yes, 4 shards ([pest.yml](../.github/workflows/pest.yml)) |
| Security | [tests/Feature/Security](../tests/Feature/Security) | `php artisan test --group=security` | same | yes |
| Pest browser | [tests/Browser](../tests/Browser) | part of `php artisan test` | Playwright browsers (`npx playwright install`) | yes |
| Playwright E2E (live) | [tests/e2e](../tests/e2e) | `npm run test:e2e` | Running app on `http://e2e.localhost:8000` (`E2E_BASE_URL`), MySQL, a migrated `e2e` tenant with [`E2eSeeder`](../database/seeders/E2eSeeder.php) fixtures | no |
| Paymob sandbox | [tests/e2e/plugins/order-management/README.md](../tests/e2e/plugins/order-management/README.md) | opt-in | Sandbox credentials and a public HTTPS callback | no |
| Performance | [tests/performance](../tests/performance) | Playwright config there | Running app | no |

Test isolation: [`TestCase`](../tests/TestCase.php) points tenant databases at `database/testing/tenant_p<pid>_*.sqlite` so parallel processes do not collide, and signs in the default user. HTTP calls to POS, gateways and Meta are faked in tests. The live account suite rules (unique users, no tenant resets) are in [tests/e2e/auth/README.md](../tests/e2e/auth/README.md).

## Debugging

- Logs: `storage/logs/laravel.log`; `php artisan pail` streams them. Tenant code logs to the same file.
- Debugbar is on when `DEBUGBAR_ENABLED=true`.
- Run code in a tenant: `php artisan tinker --execute="tenancy()->initialize('kofe'); ..."` or `php artisan tenants:run <command> --tenants=kofe`.
- Failed jobs: `php artisan queue:failed`. POS deliveries have their own attempt history in the order view; use the order's retry action rather than `queue:retry` ([POS](features/pos-integration.md#troubleshooting)).
- Plugin state: `php artisan tenant:plugin:list <tenant>`.

## Continuous integration

| Workflow | Trigger | What it checks |
| --- | --- | --- |
| [pest.yml](../.github/workflows/pest.yml) | push | `npm ci`, Playwright browsers, `composer install`, `migrate`, `php artisan test` in 4 shards (PHP 8.3) |
| [phpstan.yml](../.github/workflows/phpstan.yml) | push | PHPStan |
| [pint.yml](../.github/workflows/pint.yml) | push | Pint code style |
| [engineering-atlas.yml](../.github/workflows/engineering-atlas.yml) | push/PR | Atlas adapter tests and `atlas.py check .` (generated architecture explorer is fresh) |

There is no deployment workflow; see [Deployment](deployment.md).

## Documentation

This handbook is maintained with the `engineering-atlas` skill in `.agents/skills/engineering-atlas`. When you change behavior, interfaces, configuration, data or deployment, update the affected pages and `CHANGELOG.md`, then:

```bash
python .agents/skills/engineering-atlas/scripts/docs_check.py .
```

The generated architecture explorer (`docs/engineering-atlas/`) is rebuilt with the Laravel/React adapter described in `.agents/skills/engineering-atlas/references/adapters/laravel-react.md`.
