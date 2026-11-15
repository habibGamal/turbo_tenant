# Live-Only E2E Test Suite for Frontend React, Plugins & Multi-Theme Architecture

## Goal Description
Build a production-grade, high-speed End-to-End (E2E) test suite targeting all React storefront pages and all 13 decoupled plugins in **Live Mode Only**. 

All test scenarios execute pure end-to-end user journeys through the browser UI against the live Laravel multi-tenant backend (`http://kofe.localhost:8000`) and MySQL database—without mock data or API shortcuts.

The architecture enforces a strict **Theme Contract Specification** using standardized `data-testid` attributes documented in `docs/THEME_TEST_IDS_SPEC.md`. Any current theme (`default`) or future themes (`modern`, `minimal`, etc.) adhering to this specification will automatically be 100% verified by the exact same test suite.

## File Structure
- `docs/THEME_TEST_IDS_SPEC.md`: Standard element identifiers required across current and future themes.
- `package.json`: `@playwright/test` devDependency and test scripts.
- `playwright.config.ts`: Multi-tenant live configuration, host resolver rules (`--host-resolver-rules=MAP *.localhost 127.0.0.1`), parallel workers.
- `tests/e2e/themes/theme.contract.ts`: Standard UI contracts for all plugin elements.
- `tests/e2e/themes/theme-factory.ts`: Active theme resolver factory.
- `tests/e2e/themes/base/BaseThemeAdapter.ts`: Base semantic POM adapter.
- `tests/e2e/themes/default/index.ts`: Default theme implementation.
- `tests/e2e/helpers/live-fixture.ts`: Custom Playwright test fixture injecting theme & auto tracker blocking.
- `tests/e2e/plugins/*.live.spec.ts`: 13 live plugin test specs.

## Live E2E database fixtures

Use a **dedicated local tenant** with ID `e2e`; this seeder changes its test customer, products and order and must not be used with real data. Provision the tenant and a domain (for example `e2e.localhost`) using the application's usual tenant setup, and activate/migrate the storefront plugins first. The tenant and its central plugin activation records are not created by this seeder.

```powershell
php artisan tenants:plugin:migrate --tenant=e2e
php artisan tenants:seed --tenants=e2e --class=Database\Seeders\E2eSeeder
$env:E2E_BASE_URL = 'http://e2e.localhost:8000'
npm run test:e2e
```

The seeder is restricted to the `e2e` tenant outside PHPUnit and refuses production. It refuses to overwrite unrelated products at IDs 5, 15 and 16. It creates the suite's `admin@example.com` / `password` test customer, sample order and delivery area, categories and products including the weighted coffee, variants and extras. Running it again updates its fixtures; it does not reset carts, test-created orders, or other tenant data. The suite still requires a running Laravel server, built frontend assets and active plugins; a seed alone cannot fix application or test failures.

The seeder creates a random integration token if the dedicated tenant does not already have one. For `http://e2e.localhost`, the status lifecycle test reads that token locally and creates one if necessary; no shell variable is needed. For any other test URL, set `E2E_INTEGRATION_TOKEN` to the tenant's configured token. When this variable is set during seeding, it replaces the dedicated tenant's existing token. The test verifies that unauthenticated status updates are rejected and that authenticated updates follow the allowed transitions.

The fixtures include an empty Coffee Tools category, an About Us page, and more than 50 active products for pagination. On a fresh MySQL tenant, plugin activation currently has migration ordering problems: the orders migration references coupons before the coupons plugin can be activated, and the extras quantity column is supplied by the POS Integration migration. These migration issues must be addressed during tenant provisioning before this seeder can run.

