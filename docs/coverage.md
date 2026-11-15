# Documentation coverage

Reviewed against commit `d064a36` on branch `refactoring` plus 764 uncommitted paths in the working tree, on 2026-11-06. Everything below describes the working tree as it was then.

**How this was verified.** Every page was written from reading the linked source: routes, controllers, FormRequests, services, pipelines, models, migrations, Filament resources, the SPA router and plugin code, tests, CI workflows and git history. Commands that were run: the documentation skill's `discover.py`, `changelog_draft.py` and `docs_check.py` (structure, links, anchors), the atlas `check`, Mermaid parsing of all 24 diagrams, and the PHP test suite. The application was not started, and no browser, live gateway, POS or production system was used.

**Test suite on this working tree** (`php artisan test --parallel`, 16 processes, ~15 min): 1478 passed, 59 failed. Failures cluster in `WeightOptionResourceTest` (17), `TenantResourceTest` (14), `PlaceOrderServiceTest` (4), `PasswordResetTest` (4), Filament login/user browser tests (`is_admin` attribute not retrieved), plugin boundary architecture tests (5), `PaymentWebhookSecurityTest` (2), plus single failures in `PaymentWebhookServiceTest`, `OrderValidatorTest`, `TenantIsolationTest`, `DashboardWidgetsTest`, `EmailVerificationTest`, `ArchTest`. They come from in-progress uncommitted application changes; documentation describes the code, so behavior covered by these tests may still change.

## Pages

| Page | Status | Verified by | Gaps and next step |
| --- | --- | --- | --- |
| [Overview](overview.md) | complete | source review | — |
| [Getting started](getting-started.md) | complete | source review of commands, seeders, config | The full clean-machine sequence was not executed end to end; run it once on a fresh clone and fix any drift |
| [Architecture](architecture.md) | complete | source review | — |
| [Data model](data-model.md) | complete | migration and model review | Not compared with a live database |
| [Interfaces](interfaces.md) | complete | route files and controllers | Response bodies are summarized, not schema-complete; an OpenAPI spec would make them canonical |
| [Configuration](configuration.md) | complete | `.env.example`, `config/*`, setting enums | — |
| [Development guide](development.md) | complete | composer/npm scripts, CI, tests | — |
| [Deployment](deployment.md) | recommended procedure | derived from code requirements | No production procedure in the repository; see open decisions there |
| [Operations](operations.md) | complete for evidenced failure modes | source review | No monitoring/alerting/backups exist to document |
| [Changelog](../CHANGELOG.md) | complete from git history | `git log` (93 commits), working-tree inspection | No release tags; months used. Unreleased lists only work verified as uncommitted files |

## Features

| Feature | User guide | Technical flow | Verified by |
| --- | --- | --- | --- |
| [Store provisioning](features/tenant-provisioning.md) | complete | complete | source review |
| [Plugin marketplace](features/plugin-marketplace.md) | complete | complete | source review |
| [Store settings](features/store-settings.md) | complete | complete | source review |
| [Customer accounts](features/customer-accounts.md) | complete | complete | source review |
| [Catalog](features/catalog.md) | complete | complete | source review |
| [Combo packages](features/combo-packages.md) | complete | complete | source review; feature/integration tests through cart API, order snapshot and POS payload (2026-11-07). Not verified: browser/RTL, live POS, MySQL migration run |
| [Media optimizer](features/media-optimizer.md) | complete | complete | source review |
| [Cart](features/cart.md) | complete | complete | source review |
| [Checkout and orders](features/checkout-and-orders.md) | complete | complete | source review |
| [Locations](features/locations-delivery.md) | complete | complete | source review |
| [Guest checkout](features/guest-checkout.md) | complete | complete | source review |
| [Payments](features/payments.md) | complete | complete | source review |
| [Coupons](features/coupons.md) | complete | complete | source review |
| [POS integration](features/pos-integration.md) | complete | complete | source review + existing [design doc](order-pos-delivery-monitor.md) |
| [Favorites](features/favorites.md) | complete | complete | source review |
| [Product reviews](features/product-reviews.md) | complete | complete | source review |
| [Hero banners](features/hero-banners.md) | complete | complete | source review |
| [CMS pages](features/cms-pages.md) | complete | complete | source review |
| [Push notifications](features/push-notifications.md) | complete | complete | source review |
| [Meta marketing](features/meta-marketing.md) | complete | complete | source review |
| [Sample greeting](features/sample-greeting.md) | n/a | complete | source review |

Storefront labels come from `resources/js/locales/en` and admin labels from Filament resources; they were not checked in a rendered UI.

## Findings worth acting on

These came up while documenting. Each is described on the linked page.

| Finding | Impact | Page |
| --- | --- | --- |
| `DB_QUEUE_CONNECTION` unset → `database` queue may write to tenant DBs | Jobs invisible to a central worker | [Configuration](configuration.md) |
| `.env.example` defaults (`sqlite`, `CACHE_STORE=database`) do not work for tenant provisioning / tagged cache | New developers get errors | [Getting started](getting-started.md) |
| Work hours checked in UTC | Store appears closed at wrong times | [Checkout](features/checkout-and-orders.md#troubleshooting) |
| `app.service_rate` undefined | Service fee pipe always 0 | [Checkout](features/checkout-and-orders.md#troubleshooting) |
| Store secrets stored in plain text | Exposure via tenant DB access | [Configuration](configuration.md#store-settings-per-tenant) |
| Any central user can open `/central` | No role check on the operator panel | [Overview](overview.md#actors-and-roles) |
| `maintenance_mode` setting not enforced | Switch has no effect | [Store settings](features/store-settings.md#configuration) |
| Scheduled-notification command reads the central table | Duplicate `bulk_notifications` table, no-op command | [Push notifications](features/push-notifications.md#how-it-works) |
| Favorites API returns fixed rating 4.5; favorites only in browser | Misleading rating, no cross-device favorites | [Favorites](features/favorites.md) |
| `PAYMOB_*` env vars and `config/paymob.php` unused | Confusing configuration | [Configuration](configuration.md#integrations) |
| Review auto-approve setting has no admin field | Must be set by hand | [Product reviews](features/product-reviews.md#configuration) |
| 29 tenant migrations duplicated between host and plugin folders | Edits to one copy may be skipped | [Data model](data-model.md#stores) |

## Open questions

- Production hosting, process supervision, backups and TLS for custom domains (blocks: [Deployment](deployment.md#open-decisions); ask: platform owner).
- Whether the `refactoring` branch's uncommitted work ships as one release and under which version (blocks: changelog version heading; ask: maintainers).
- Which POS vendor(s) implement the contract in [CREATE_ORDER_API_DOC.md](../CREATE_ORDER_API_DOC.md), and whether they deduplicate by `orderNumber` (blocks: automatic retry of uncertain deliveries; ask: POS vendor).
