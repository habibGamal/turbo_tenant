# Module 8 — Delegation Progress (agy / gemini-3.8-flash-high)

**Status 2026-10-28: every item in plans 01–08 is implemented and committed on `refactoring`. Remaining items are the open follow-ups at the end.**

Implementer: Antigravity CLI (`agy` 1.4.4), model `gemini-3.8-flash-high`, full access. Reviewer: Claude.
Nothing is committed: the working tree already holds the owner's uncommitted refactoring, and another session is editing models/tests at the same time.

| Task | Scope (from 08_gap_audit) | Status |
| --- | --- | --- |
| T1 | B1–B4 storefront bugs + shared `lib/pricing.ts` | ✅ reviewed |
| T2 | A2–A4 order alerts, status tabs, new-order DB notifications | ✅ reviewed |
| T3 | A1 + A12 dashboard widgets, setup checklist | ✅ reviewed |
| T4 | B5–B7, B9 checkout feedback, toasts | ✅ round 2 lane L6 (edc34c4) |
| T5 | A5–A11 admin localization / panel shell | ✅ folded into lanes L1–L5 |
| T6 | B10–B11 order tracking, accessibility | ✅ round 2 lane L7 (edc34c4) |
| T7 | B12–B15 money, RTL, tokens polish | ✅ round 3 lanes L9 (533c6c3), L10 (fabc50e), L11 (0821d04); helper `lib/money.ts` (0543c4d) |
| T8 | B8 mobile menu redesign | ✅ round 2 lane L8 (0ef30b3) |

## Round 2 (2026-10-28): 8 parallel lanes, one shared working tree, disjoint file ownership

| Lane | Scope | Status |
| --- | --- | --- |
| L1 | CatalogProducts: Phase 2 + Phase 1/Module 7 rows + A6/A10/A11 | ✅ ce5e0db |
| L2 | OrderManagement + order enums: Phase 3 + Phase 1 §2.D + Module 7 + A5/A6/A11 | ✅ 6c39210 |
| L3 | Coupons, Packages, HeroBanners, CmsPages, PushNotifications: Phase 4 + rows + A6/A11 | ✅ 5aeab88 |
| L4 | LocationsDelivery, GuestCheckout, ProductReviews, Users: Phase 5 + rows + A6/A7/A11 | ✅ 226fe55 |
| L5 | StoreSettings, POS pages, ManagePlugins, AdminPanelProvider: Phase 6 + A8/A9/A10 + group order | ✅ c1cd52b |
| L6 | Storefront T4: B5, B6, B7, B9 | ✅ edc34c4 |
| L7 | Storefront T6: B10, B11 | ✅ edc34c4 |
| L8 | Storefront T8: B8 (+ menu bits of B15) | ✅ 0ef30b3 |

Pre-work (commit bde3447): `SettingKey::ALLOW_MANUAL_ORDER_STATUS_UPDATE` added up front so L2/L5 don't share the enum; two boot fatals fixed (`GuestCustomer::getKey()` signature, cart request shims extending final classes).
Baseline test health (pre-existing, not from this work): full run = 448 failed / 220 passed, 420 of them `table "migrations" already exists`; files also fail alone (WeightOptionResourceTest 17/31). Lanes gate on before/after counts per file.

## Review notes

### T1
- Files: `hooks/useProductOptions.ts`, `lib/pricing.ts` (new), `ProductOptions.tsx`, `ProductOptionsModal.tsx`, `ThemedProductShow.tsx`, `ProductCard.tsx`, `ProductInfo.tsx`, `ProductImage.tsx`, `SearchBar.tsx`, `MainLayout.tsx`, `pages/ProductShow.tsx` (prop pass-through only), both translation files.
- Verified: `tsc --noEmit` 0 errors; diff read line by line; `allow_multiple=false` = single choice matches backend `ProductValidator`.
- Review round: first pass copy-pasted the discount check in 8 places → sent back, now one helper.

### T2
- Files: `OrderResource.php` (pending badge), `ListOrders.php` (status tabs incl. POS-failed), `ViewOrder.php` (dead Edit removed, Arabic resend), `OrdersTable.php` (poll 30s, `since()`), `AdminPanelProvider.php` (DB notifications + polling), new `Listeners/NotifyAdminsOfNewOrder.php`, `OrderManagementServiceProvider.php`, new tenant migration `2026_10_25_000000_create_notifications_table.php`, `User.php`, `NotificationController.php`, tests.
- Review round: first pass made `User::notifications()` switch relation type on `is_admin` (admins would lose their storefront push feed). Sent back → storefront feed renamed to `userNotifications()` / `unreadUserNotifications()`, Laravel's standard `notifications()` restored.
- Verified myself: NotificationControllerTest + OrderResourceTest + UserNotificationTest = 30 passing. `--filter=Order` has 22 failures that come from the other session's in-progress pricing/cart-route changes, not from T2.
- ⚠️ Run `php artisan tenants:migrate` to create the `notifications` table on existing tenants.

### T3
- New widgets: `OrdersStatsOverview`, `LatestOrdersTable`, `TopProductsChart` (OrderManagement), `PosFailedOrdersTable` (PosIntegration, with resend), `PendingReviewsStat` (ProductReviews), core `StoreSetupChecklist` + Blade view. `FilamentInfoWidget` and `AccountWidget` removed from the panel.
- Review round: fixed a division-by-zero in the vs-yesterday comparison (float `0.0 === 0` is false), pending stat now neutral/warning, checklist checks `PluginManager::isPluginActive()` instead of only `class_exists`.
- Verified myself: DashboardWidgetsTest 10 passing.

## Needs your eyes
- **Plugin scoping (pre-existing, not changed):** `AdminPanelProvider` registers every discovered plugin's Filament plugin for all tenants, so resources/widgets of a plugin a tenant has turned off still appear. `PluginManager::getActiveFilamentPluginsForTenant()` exists but isn't used there.
- Phase 2 plan labels `allow_multiple` as "السماح بتكرار نفس الخيار" (repeat same option). In the backend it means "more than one distinct extra allowed". Fix the label when implementing Phase 2.


### Round 2 review fixes (applied by the reviewer before committing)
- L1: product selection-rule fields are "empty = inherit" (no copying of the extra option's values); cart `ProductValidator` now applies product-level rules (+ test).
- L2: order global search no longer queries the non-existent `users.phone` (crashed the panel search); removed hidden `data-*` attributes added only to satisfy an old test, test now asserts Arabic labels.
- L3: fixed `BulkNotificationFactory` enum import instead of a `class_alias` shim.
- L4: real `User::orders()` relation; user phone comes from `addresses.phone_number` (no `users.phone` column exists); removed runtime `Schema::hasColumn` checks.
- L5: branding closures consolidated; `PosFailedOrdersTable` + test pointed at the real `OrderPosStatus` enum.
- L6/L8: single add-to-cart toast (from the hook) + error toast; mobile checkout bar sits above the bottom nav.
- L7: product card image link removed from tab order (title link is the focus target).
- e2e specs/adapter updated for the clear-cart confirm, inline checkout errors, link-based card navigation.
- Reviewed related PHP tests: 361 passing across the new UX tests and the existing tests of touched plugins.

### Open follow-ups (not in any lane)
- Product `sort_order` for menu ordering needs a backend column + API sort (L8 sorts client-side when present).
- No reorder API, so "اطلب مرة أخرى" was skipped.
- No brand-color setting, so the panel stays `Color::Blue`.
- `VerifyCsrfToken` is off in the admin panel (commit 2c31a1d, for cross-subdomain Livewire uploads).
- A13 shims (`app/Filament/Resources/HeroSliderResource.php`, `app/Filament/Forms/Components/PosItemSelect.php`) still exist; ProductForm/ExtraOptionForm import the shim.
- Pre-existing: full-suite runs fail with `table "migrations" already exists` (order-dependent); `LoginTest` 3 failures (`is_admin` missing attribute).
- Run `php artisan tenants:migrate` for the new products selection-rule columns.
