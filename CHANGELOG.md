# Changelog

Notable changes to Turbo Tenant, newest first. Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The project has no release tags; history is grouped by month from the git log on branch `refactoring` (93 commits, 2025-11-10 to 2026-10-28). `Unreleased` describes work present in the working tree but not yet committed.

## [Unreleased]

### Added
- Shared docs theme check: `scripts/docs-theme.sh` (verify / lock / pull) and `docs/.vitepress/theme.lock` keep the theme identical across the POS, website and platform docs; `docs-check.sh` fails when they differ. The style guide explains how to run the three sites together.
- Shared VitePress docs theme and six-section layout (`start/`, `guide/`, `admin/`, `integrations/`, `dev/`, `dev/ops/`): Arabic store admin guide as the default `/` locale with generated glossary (`docs/start/glossary.md`) and Arabic integration how-to pages (`docs/integrations/`), English developer handbook under `docs/dev/`, `DOCS_PUBLIC=1` public-only build mode, client-side redirects for legacy `/ar/` and English URLs, shared `docs/dev/style-guide.md`, and updated `scripts/docs-check.sh` dialect/build checks.
- Production web server page: nginx vhost for tenant images (host → tenant `map` for custom domains, `disable_symlinks off;` on the image locations), the 2026-11-21 image 404 incident, and a checklist to run after every `git pull` on production ([docs](docs/dev/ops/production-server.md)).
- Documentation website (VitePress, `docs/`): the technical handbook in English (`docs/dev/`) plus the Arabic store admin guide (right-to-left) covering setup, catalog, sizes, add-ons, weight options, packages, branches and delivery areas, orders, POS connection, payments, coupons, content, notifications, Meta and FAQ. New technical pages: [Connecting a POS to the website](docs/dev/pos-website-link.md) and [Queue, workers and scheduler](docs/dev/ops/queue-and-workers.md) ([development guide](docs/dev/development.md#documentation)).
- Developer handbook in `docs/dev/` with a page per feature, integration guides (`docs/dev/integrations/`), [security overview](docs/dev/security.md), [themes and test IDs](docs/dev/themes.md), deployment and operations guides, and this reconstructed changelog ([docs](docs/dev/index.md)).
- Repository docs cleanup: consolidated root and `.github/docs/` Markdown files into `docs/`, extracted open `.plan/` items into `.workspace/specs/`, added committed `.workspace/` tracking and `CLAUDE.md`, moved reusable skills to `.claude/skills/`, dropped the `engineering-atlas` skill/output, and added `scripts/docs-check.sh` with `.github/workflows/docs-check.yml` CI checks.
- Meta marketing plugin: Conversions API purchase and relayed browser events, Meta catalog feed (CSV/XML, Arabic override) and a diagnostics page ([docs](docs/dev/features/meta-marketing.md)).
- POS delivery tracking: per-order delivery state, attempt history, uncertain-delivery verification by admins, overdue filter and failed-orders widget ([docs](docs/dev/features/pos-integration.md)).
- Combo packages are purchasable end to end ([docs](docs/dev/features/combo-packages.md)):
  - customer choices, sizes, add-ons and per-item notes;
  - server-side pricing and order component snapshots;
  - POS delivery as a package-level product or as component products;
  - coupon targeting and per-package coupon exclusion;
  - weekly availability, branch, order-type, per-order and daily limits;
  - an admin health column, sales widgets and a product-page upsell.
  New tenant migrations land in CartEngine, OrderManagement, PosIntegration and ComboPackages. **Rolling back the cart/order migrations is unsafe once package lines exist.**
- Plugin contribution points (bootstrap data, settings tabs, order rules/resolvers/responses, cart line types, admin form/table/checklist extensions) and settings tabs contributed by plugins; theme presets for the storefront ([architecture](docs/dev/architecture.md#extension-points)).
- Commands `tenants:plugin:migrate`, `plugin:clear` and `serve:fast`.
- Uniform JSON error envelope for `api/*` requests.
- Per-tenant storefront conversion & shipping controls: automatic free shipping threshold (`free_shipping_threshold`) enforced in checkout pricing with progress bar in cart/checkout, simplified single-field checkout address toggle (`simplified_address_enabled`), and floating WhatsApp CTA (`whatsapp_number`) ([docs](docs/dev/features/checkout-and-orders.md), [docs](docs/dev/features/store-settings.md)).
- Mobile product page sticky add-to-cart bar (`ThemedProductShow`) and compact mobile product image height (`ProductImage`).

### Changed
- POS API key fields in the admin now say where to copy the key on the POS (الإعدادات › إدارة الفروع › مفتاح الربط (API)).
- Plugin Marketplace (`/admin/plugins`) now uses a native Filament Table with `PluginStatsOverview` header stats, status tabs, category filter, and per-row lifecycle actions ([docs](docs/dev/features/plugin-marketplace.md)).
- Checkout hides the branch selector when only one active branch exists, syncs branch from the selected delivery area, hides the mobile bottom nav bar on `/checkout` so the sticky place-order bar anchors cleanly to the bottom, and displays localized deduplicated governorate/area labels with shipping fees (`locationFormatters.ts`).
- POS calls send `X-Api-Key`, which the POS now requires on its website endpoints. Each branch has a POS API key (`branches.pos_api_key`, encrypted, new PosIntegration tenant migration), and the master POS key is the `products_repo_api_key` setting ([docs](docs/dev/features/pos-integration.md#configuration)). The branch form gained the `admin.form.locations-delivery.branch` contribution point.
- Coupons can target line types (e.g. packages).

### Fixed
- POS orders: the chosen size (variant) now always reaches the POS in the item notes («النوع»), even when the size is mapped to a POS item; before, a mapped size was dropped and the cashier could not see it ([docs](docs/dev/pos-website-link.md)).
- Takeaway web orders can be completed: the POS status callback (and the already-received catch-up) now accepts `processing → completed` for `web_takeaway` orders. Before, the website answered 409 and the POS rolled its completion back ([docs](docs/dev/pos-website-link.md#contract)).
- Meta Purchase (Pixel and Conversions API): weighted lines are sent as quantity 1 (not a fractional kg quantity) at the catalog unit price, and `num_items` counts each weighted line as one unit ([docs](docs/dev/features/meta-marketing.md)).
- Cash-on-delivery orders are marked paid («مكتمل (مدفوع)») when they are completed, including when the POS reports the completion ([docs](docs/dev/features/checkout-and-orders.md#flow)).
- POS add-on lines carry a short note naming the item they belong to («إضافة على: كوف محوج») and nothing else ([docs](docs/dev/features/pos-integration.md#flow)).
- POS orders now read like the website order: each mapped add-on is its own POS line counted per piece (or per weight multiplier for items sold by weight, never x the weight in kg) with no copied notes; add-on and variant names in notes and the delivery address (labels, area, governorate) are in Arabic ([docs](docs/dev/features/pos-integration.md#flow)).
- Retried POS deliveries no longer fail on a duplicate order: the site sends a stable `idempotencyKey`, a POS that already saved the same order answers `already_received` and the site marks it sent and catches up its status; a different order under the same number fails with the mismatched fields instead of retrying ([docs](docs/dev/features/pos-integration.md#flow)).
- Prevent central database foreign key integrity violation (`1452`) on `tenant_plugins` during tenant creation by self-healing missing central catalog records in `PluginLifecycleService::activate`.
- Prevent tenant dashboard 500 query exceptions (`42S02`) on new tenant accounts by gating `PendingReviewsStat` and `OrderManagement` widgets with tenant plugin activation and schema table existence checks.
- Resolve cross-plugin circular dependency in `orders` tenant migration: decoupled `coupon_id` foreign key from `OrderManagement` and added idempotency guards (`Schema::hasTable`) to prevent `1824` / `1050` table creation crashes.
- Storefront UX/UI, RTL/BiDi, accessibility, and checkout fixes across the default React theme and UI primitives: fixed coupon validation hitting `/coupons/validate` (`405`) instead of `/api/coupons/validate`, prevented unauthenticated `/orders` 401 errors with a guest sign-in/tracking prompt, added Meta CAPI `419` XSRF refresh retry, aligned mobile drawers/sheets and Sonner toasts with RTL direction and z-index layering above mobile bottom navigation, fixed hero contrast/dots/badge clipping, standardized `44px` mobile touch targets, localized weight/area/error/review formatting, and eliminated duplicate mobile checkout CTAs.
- Guest payment callback **View Order** (`عرض الطلب`) and secondary links now route guests directly to signed order tracking (`/orders/track?order_number=...&token=...`) instead of `/orders/:id` ("Sign in to view your orders") ([docs](docs/dev/features/payments.md#how-it-works)).

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
- Features reorganized into per-tenant plugins with dependency-ordered activation and activation-gated event listeners ([docs](docs/dev/features/plugin-marketplace.md)).
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
- Guest checkout: ordering without an account, order tracking, guest emails, admin list ([docs](docs/dev/features/guest-checkout.md)).
- Arabic names/labels for categories, sections, products, variants, extras, governorates, areas and site settings; Arabic admin resources.
- Checkout components: order type, payment method, notes, order summary.
- Social links management in the footer and navigation.
- Quick-add variants bulk action for products; sample governorate/area seeder.

## 2026-01 (untagged)

### Added
- Kashier payment gateway ([docs](docs/dev/features/payments.md)).

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
