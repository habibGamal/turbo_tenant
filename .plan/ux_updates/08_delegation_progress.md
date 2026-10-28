# Module 8 — Delegation Progress (agy / gemini-3.8-flash-high)

Implementer: Antigravity CLI (`agy` 1.4.4), model `gemini-3.8-flash-high`, full access. Reviewer: Claude.
Nothing is committed: the working tree already holds the owner's uncommitted refactoring, and another session is editing models/tests at the same time.

| Task | Scope (from 08_gap_audit) | Status |
| --- | --- | --- |
| T1 | B1–B4 storefront bugs + shared `lib/pricing.ts` | ✅ reviewed |
| T2 | A2–A4 order alerts, status tabs, new-order DB notifications | ✅ reviewed |
| T3 | A1 + A12 dashboard widgets, setup checklist | ✅ reviewed |
| T4 | B5–B7, B9 checkout feedback, toasts | ⏳ at implementer |
| T5 | A5–A11, A13 admin localization / panel shell | queued |
| T6 | B10–B11 order tracking, accessibility | queued |
| T7 | B12–B15 money, RTL, tokens polish | queued |
| T8 | B8 mobile menu redesign | queued |

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
