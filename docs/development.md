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
| [`docs/`](README.md) | This handbook and the Arabic store admin guide (`docs/ar/`) |
| [`.claude/skills/`](../.claude/skills) | Project-specific agent skills (`plugin-builder`, `plugin-inertia-to-api`, `e2e-test`) |

Do not hand-edit: `bootstrap/cache/*.php` (plugin and framework caches), `public/build`, `vendor/`, `node_modules/`.

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
9. **Docs:** add `docs/features/<slug>.md`, a catalog row, config entries and an `Unreleased` changelog entry; run `bash scripts/docs-check.sh`.

## Storefront localization (i18n)

- **Configuration:** [`resources/js/i18n.ts`](../resources/js/i18n.ts) configures `i18next` with `ar` (Arabic) as default/fallback language and `en` (English) as secondary language, detecting language in `localStorage` and `cookie` (not browser navigator, so first-time visitors default to Arabic).
- **Direction (`dir`/`lang`):** changing language automatically updates `<html dir="rtl|ltr">` and `<html lang="ar|en">` in `i18n.ts` and [`useLanguage.ts`](../resources/js/hooks/useLanguage.ts). Use Tailwind `rtl:` / `ltr:` and logical spacing utilities (`ms-`, `me-`, `ps-`, `pe-`).
- **Translation keys:** store keys in both [`resources/js/locales/ar/translation.json`](../resources/js/locales/ar/translation.json) and [`resources/js/locales/en/translation.json`](../resources/js/locales/en/translation.json) and read them with `const { t } = useTranslation(); t('key')`. Do not hardcode bilingual ternaries in components.
- **Language switcher:** [`LanguageSwitcher.tsx`](../resources/js/components/LanguageSwitcher.tsx) and `useLanguage()` expose language switching to theme headers/menus.

## Testing

| Suite | Location | Command | Needs | In CI |
| --- | --- | --- | --- | --- |
| Unit | [tests/Unit](../tests/Unit) | `php artisan test --testsuite=Unit` | nothing | yes |
| Feature / API / Filament | [tests/Feature](../tests/Feature) | `php artisan test` | SQLite (tenant DB file per process in `database/testing/`) | yes, 4 shards ([pest.yml](../.github/workflows/pest.yml)) |
| Security | [tests/Feature/Security](../tests/Feature/Security) | `php artisan test --group=security` | same | yes |
| Pest browser | [tests/Browser](../tests/Browser) | part of `php artisan test` | Playwright browsers (`npx playwright install`) | yes |
| Playwright E2E (live) | [tests/e2e](../tests/e2e) | `npm run test:e2e` | Running app on `http://e2e.localhost:8000` (`E2E_BASE_URL`), MySQL, a migrated `e2e` tenant with [`E2eSeeder`](../database/seeders/E2eSeeder.php) fixtures | no |
| Auth E2E (live) | [tests/e2e/plugins/auth](../tests/e2e/plugins/auth) | `npm run test:e2e:auth` | Built storefront (`npm run build`), `e2e.localhost` tenant with `E2eSeeder` fixtures, MySQL, `php` on PATH | no |
| Paymob sandbox E2E | [tests/e2e/plugins/order-management/19-paymob-sandbox.live.spec.ts](../tests/e2e/plugins/order-management/19-paymob-sandbox.live.spec.ts) | opt-in (`E2E_PAYMOB_SANDBOX=1`) | Sandbox credentials and a public HTTPS callback | no |
| Performance | [tests/performance](../tests/performance) | Playwright config there | Running app | no |

Test isolation: [`TestCase`](../tests/TestCase.php) points tenant databases at `database/testing/tenant_p<pid>_*.sqlite` so parallel processes do not collide, and signs in the default user. HTTP calls to POS, gateways and Meta are faked in tests.

### Live account browser tests

`npm run test:e2e:auth` starts a loopback-only PHP server per test with its own cache prefix and temporary log mailer directory (removed after each run; screenshots/videos/traces are disabled in password recovery specs so reset links are not saved). It refuses non-`e2e` tenant IDs and non-local environments, creates uniquely named `auth-e2e-<uuid>@example.test` customers, and cleans up only its own records without touching `admin@example.com`. Profile phone persistence is marked expected-fail because the profile endpoint does not save `phone`; Google login is tested up to the local handoff link.

### Paymob sandbox E2E

`19-paymob-sandbox.live.spec.ts` is skipped unless `E2E_PAYMOB_SANDBOX=1`. Required env vars: `E2E_BASE_URL` (public HTTPS tenant URL), `E2E_PAYMOB_SANDBOX=1`, `E2E_PAYMOB_PRODUCT_ID`, `E2E_PAYMOB_TEST_PHONE`, `E2E_PAYMOB_TEST_CARD_NUMBER`, `E2E_PAYMOB_TEST_CARD_EXPIRY` (`MMYY`), `E2E_PAYMOB_TEST_CARD_CVV`, and `E2E_PAYMOB_TEST_CARDHOLDER`. Traces, screenshots, and video are disabled for this spec.

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
| [docs-check.yml](../.github/workflows/docs-check.yml) | PR / push (`refactoring`) | `bash scripts/docs-check.sh --build`: allowed `.md` paths, Arabic dialect check, VitePress build |

There is no deployment workflow; see [Deployment](deployment.md).

## Documentation

`docs/` (VitePress, technical handbook + Arabic store admin guide in `docs/ar/`) is the single source of truth. When you change behavior, interfaces, configuration, data, or deployment, update the affected pages and `CHANGELOG.md`, then validate:

```bash
bash scripts/docs-check.sh
```

To run or build the documentation site locally (uses its own `docs/package.json` without affecting app dependencies):

```bash
cd docs
npm install
npm run dev       # http://localhost:5173
npm run build     # static site in docs/.vitepress/dist
npm run preview
```

Links between pages use relative `.md` paths; links to source files (`../app/...`) are rewritten to GitHub (`refactoring` branch) at build time. Arabic pages use `<span class="path">...</span>` for admin paths, `<div class="steps">` for numbered steps, and Modern Standard Arabic.
