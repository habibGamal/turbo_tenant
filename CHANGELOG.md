# Changelog

Notable changes to Turbo Tenant, newest first. Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The project has no release tags; history is grouped by month from the git log on branch `refactoring` (93 commits, 2025-11-10 to 2026-10-28). `Unreleased` describes work present in the working tree but not yet committed.

## [Unreleased]

### Added
- Developer handbook in `docs/` with a page per feature, deployment and operations guides, and this reconstructed changelog ([docs](docs/README.md)). The documentation skill can now handle any stack and includes `docs_check.py`.
- Meta marketing plugin: Conversions API purchase and relayed browser events, Meta catalog feed (CSV/XML, Arabic override) and a diagnostics page ([docs](docs/features/meta-marketing.md)).
- POS delivery tracking: per-order delivery state, attempt history, uncertain-delivery verification by admins, overdue filter and failed-orders widget ([docs](docs/features/pos-integration.md)).
- Combo packages are purchasable end to end ([docs](docs/features/combo-packages.md)):
  - customer choices, sizes, add-ons and per-item notes;
  - server-side pricing and order component snapshots;
  - POS delivery as a package-level product or as component products;
  - coupon targeting and per-package coupon exclusion;
  - weekly availability, branch, order-type, per-order and daily limits;
  - an admin health column, sales widgets and a product-page upsell.
  New tenant migrations land in CartEngine, OrderManagement, PosIntegration and ComboPackages. **Rolling back the cart/order migrations is unsafe once package lines exist.**
- Plugin contribution points (bootstrap data, settings tabs, order rules/resolvers/responses, cart line types, admin form/table/checklist extensions) and settings tabs contributed by plugins; theme presets for the storefront ([architecture](docs/architecture.md#extension-points)).
- Commands `tenants:plugin:migrate`, `plugin:clear` and `serve:fast`.
- Uniform JSON error envelope for `api/*` requests.

### Changed
- POS calls send `X-Api-Key`, which the POS now requires on its website endpoints. Each branch has a POS API key (`branches.pos_api_key`, encrypted, new PosIntegration tenant migration), and the master POS key is the `products_repo_api_key` setting ([docs](docs/features/pos-integration.md#configuration)). The branch form gained the `admin.form.locations-delivery.branch` contribution point.
- Coupons can target line types (e.g. packages).

### Fixed
- Prevent central database foreign key integrity violation (`1452`) on `tenant_plugins` during tenant creation by self-healing missing central catalog records in `PluginLifecycleService::activate`.
- Prevent tenant dashboard 500 query exceptions (`42S02`) on new tenant accounts by gating `PendingReviewsStat` and `OrderManagement` widgets with tenant plugin activation and schema table existence checks.
- Resolve cross-plugin circular dependency in `orders` tenant migration: decoupled `coupon_id` foreign key from `OrderManagement` and added idempotency guards (`Schema::hasTable`) to prevent `1824` / `1050` table creation crashes.

### Upgrade notes
- Run `php artisan plugin:discover` and `php artisan tenants:plugin:migrate --force` so active plugins get their new tenant tables (notably POS delivery tracking and Meta tables).
- Set `DB_QUEUE_CONNECTION` to the central connection; restart queue workers after deploying.

## 2026-10 (untagged)

### Added
- Admin panel UX overhaul: catalog, orders, promotions/content, delivery/CRM, settings page tabs and navigation groups.
- Storefront: live order tracking, checkout/cart feedback and accessibility, compact mobile category menu, shared money formatter using the store currency, RTL and theme tokens across views.
- Facebook Pixel purchase tracking in checkout.
- Theme adapter architecture for end-to-end tests; feature tests for CMS pages, combo packages, favorites, hero banners and reviews.
- Store setting to allow manual order status updates.

### Changed
- Features reorganized into per-tenant plugins with dependency-ordered activation and activation-gated event listeners ([docs](docs/features/plugin-marketplace.md)).
- Storefront moved from Inertia/Ziggy to a React SPA with a theme registry and a checkout login guard.
- Deterministic test suite with a tenant database per test process and a `security` test group.

### Security
- Payments: read-only browser callback, fail-closed webhooks, explicit payment state machine, idempotent webhook processing.
- POS: order-status callbacks require the integration token; branch endpoints guarded against private addresses.
- Auth: Google ID tokens verified; passwords no longer logged.
- Plugins boot isolated per tenant.

### Fixed
- POS: TypeError on every order fixed; payment methods sent in the format the POS contract defines; place-order responses handled as documented; Scenario B orders marked failed instead of sent.
- Plugins: crashes from optional plugin relations removed.

## 2026-08 (untagged)

### Fixed
- Product image paths and default width/quality for optimized images.
- Address/area forms in admin; null addresses when building order payloads.

### Changed
- Order service charge handling.

## 2026-06 (untagged)

### Added
- Custom pages guide and project overview documentation.

## 2026-03 (untagged)

### Added
- Product availability checks in cart and order placement.
- More order details in the admin order view.

### Fixed
- POS order job retries more and backs off longer.
- Default guest billing data for online payments.

## 2026-02 (untagged)

### Added
- Guest checkout: ordering without an account, order tracking, guest emails, admin list ([docs](docs/features/guest-checkout.md)).
- Arabic names/labels for categories, sections, products, variants, extras, governorates, areas and site settings; Arabic admin resources.
- Checkout components: order type, payment method, notes, order summary.
- Social links management in the footer and navigation.
- Quick-add variants bulk action for products; sample governorate/area seeder.

## 2026-01 (untagged)

### Added
- Kashier payment gateway ([docs](docs/features/payments.md)).

## 2025-12 (untagged)

### Added
- Google sign-in for web and native apps; `app:create-tenant` command.
- Filament multi-factor authentication, Expo push notification support, multi-tenancy configuration.

## 2025-11 (untagged)

### Added
- Core commerce: catalog with variants, extras and weight-sold products; cart; orders; reviews; user management; Filament admin.
- Tenant management in the central panel with tenant setup.
- POS order service and product importer with tests.
- PWA manifest settings; image fallback component.
- Registration and password reset pages; Google OAuth with per-store settings.
- Storefront layout and home page.
