# Module 8: Gap Audit — What Phases 1–7 Miss (Filament + Default Storefront Theme)

> **Audited:** 2026-10-25, branch `refactoring`
> **Scope:** Items NOT already covered by files 00–07. Phases 1–7 are still valid; none of them are implemented yet in code (nav groups, enums, tabs, toggles, currency are all still as-is).
> **Priority key:** 🔴 bug / broken flow · 🟠 high UX impact · 🟡 polish

---

## Part A — Filament Admin Panel (panel-wide gaps)

The existing plan is resource-by-resource. What's missing is the panel shell and the "daily operator" experience.

### A1. 🔴 Dashboard is the Filament default
[AdminPanelProvider.php](../../app/Providers/Filament/AdminPanelProvider.php) shows only `AccountWidget` + `FilamentInfoWidget` (Filament version/GitHub links). `app/Filament/Widgets` doesn't exist. A store owner logs in and sees nothing about their store.

Add an `app/Filament/Widgets` set:
- **StatsOverview:** today's orders, today's revenue, avg order value, pending orders (each with a 7-day sparkline and % vs yesterday).
- **Latest orders table** (last 10, status badge, link to view).
- **POS sync failures** widget (orders with `pos_status = failed`, one-click "resend") — this is the most actionable admin task given POS owns status.
- **Pending reviews** count + link (ties into Phase 5 moderation).
- **Top products this week.**
- Remove `FilamentInfoWidget`.

### A2. 🟠 No live alert for new orders
Nothing polls or notifies. Operators must refresh.
- `OrdersTable`: `->poll('30s')`.
- `OrderResource::getNavigationBadge()` = count of `pending` orders, `getNavigationBadgeColor()` = `warning`.
- Enable `->databaseNotifications()->databaseNotificationsPolling('30s')` on the panel and send a DB notification on order creation (optional sound via a small render hook).
- Same badge pattern for Reviews (pending approval) and POS failures.

### A3. 🟠 Orders list has no status tabs
`ListOrders` should implement `getTabs()`: **الكل / قيد الانتظار / قيد التحضير / خارج للتوصيل / مكتمل / ملغي / فشل POS**, each with a count badge. Faster than the multi-select filter for the most common question ("what's open right now?").
Also: `ListOrders` still registers `CreateAction` and `ViewOrder` registers `EditAction` though `canCreate/canEdit` return false — remove both for clarity.

### A4. 🟠 Order dates aren't scannable
`created_at` uses `->dateTime()` only. Use `->since()` with `->dateTimeTooltip()` (shows "منذ 5 دقائق"), and confirm `->timezone()` uses the store timezone, not UTC.

### A5. 🟠 Order infolist & ViewOrder still in English
Phase 3 covers items/extras, but the rest of [OrderInfolist.php](../../app/Plugins/OrderManagement/src/Filament/Resources/Orders/Schemas/OrderInfolist.php) has ~20 English labels ("Order Information", "Order ID", "Merchant Order ID", "Transaction ID", `shift_id` with no label). `ViewOrder` "Resend to POS" action + notification are English. Hide gateway IDs (Paymob/merchant/transaction) in a collapsed "بيانات الدفع التقنية" section — they're support data, not operator data.

### A6. 🟠 Global search is effectively off
Only Users, Pages and Variants define a title attribute. Add `$recordTitleAttribute` + `getGloballySearchableAttributes()` to **Orders** (order_number, customer phone), **Products** (name, name_ar), **Coupons** (code), **Users** (phone). The `CTRL + K` hint is already shown, so it currently promises something it doesn't deliver.

### A7. 🟠 Users table mixes admins and customers
`users.is_admin` exists but [UsersTable.php](../../app/Filament/Resources/Users/Tables/UsersTable.php) has no split. Add tabs **العملاء / المديرين**, and a guarded `is_admin` toggle in the form (can't remove your own admin flag). Phase 5's phone/orders-count work should apply to the Customers tab only.

### A8. 🟠 No profile page / password change for admins
`->profile()` and MFA are commented out in the admin panel (both are enabled in Central). A tenant admin can't change their own password. Enable `->profile()` at minimum.

### A9. 🟡 Panel branding is generic
- `primary => Color::Blue` is hardcoded; use the tenant's brand color from Store Settings so admin matches storefront.
- Add `->brandName()`, `->brandLogo()`, `->favicon()` from Store Settings (logo already exists there).
- Consider `->spa()` (already used in Central) for faster navigation.
- Set the tenant app locale to `ar` (`.env.example` has `APP_LOCALE=en`), so Filament's own strings (pagination, "Save", "Create", filters, empty states) render in Arabic and the panel switches to RTL. Today the custom labels are Arabic but the framework chrome is English — a mixed-language panel.

### A10. 🟡 Remaining English screens not in the plan
- [ManifestSettings.php](../../app/Plugins/StoreSettings/src/Filament/Pages/ManifestSettings.php): 12 English labels, nav group `'Settings'` (English, will create an 8th group).
- [VariantsRelationManager.php](../../app/Plugins/CatalogProducts/src/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php): 7 English labels.
- POS pages use group `'المنتجات'` — will become a stray group unless Phase 1 is applied to them too.

### A11. 🟡 Empty states everywhere except POS pages
Only `ImportPOSProducts` and `SyncPOSPrices` define `emptyStateHeading/Description/Icon/Actions`. Add them to all resource tables, with a primary "create" action (e.g. Areas: "لم تضف مناطق توصيل بعد — أضف أول منطقة"). Matters most on a fresh tenant during onboarding.

### A12. 🟡 Onboarding checklist for new tenants
A new store needs: logo → branch → governorate/area → category → product → payment settings. A dashboard widget showing this checklist with done/not-done and links shortens time-to-first-order.

### A13. 🟡 Code hygiene that affects UX consistency
- Two `HeroSliderResource` files (`app/Filament/Resources` is a `class_alias` shim) and two `PosItemSelect` components — fixes applied to one won't show in the other. Delete the shims once nothing references them.

### A14. ⚠️ Not UX, but noticed
`VerifyCsrfToken` is commented out in the admin panel middleware (it's enabled in Central). Worth checking that this is intentional.

---

## Part B — Default Storefront Theme (`resources/js/themes/default`)

No plan file covers the storefront yet. Ordered by impact on conversion.

### B1. 🔴 Single-choice option groups can't change selection
[useProductOptions.ts:93-100](../../resources/js/hooks/useProductOptions.ts#L93-L100): when `max_selections` is reached and `allow_multiple` is false, the code clears and sets the new extra, then `return`s **before** `setSelectedExtras(newExtras)`. Result: on a "choose 1" group, tapping a second option does nothing; the customer must untap the first one. Fix: call `setSelectedExtras(newExtras)` before returning. When `allow_multiple` is true and max is reached, give feedback (shake + "الحد الأقصى X") instead of silently ignoring the tap.

### B2. 🔴 `min_selections` is never enforced
The UI shows "اختر X على الأقل" but the add-to-cart button is only disabled while submitting, and `handleAddToCart` doesn't validate. Customers can add a burger without a required sauce/size. Disable the button (or scroll to and highlight the group) until all required groups are satisfied. This pairs with Phase 2's product-level min/max — the storefront must read the product override, not only `extraOption.min_selections`.

### B3. 🔴 Likely: "Sale" badge shown on every non-discounted product
[ProductCard.tsx](../../resources/js/themes/default/plugins/catalog-products/components/ProductCard.tsx): `hasDiscount = Number(price_after_discount) < Number(base_price)`. `price_after_discount` is nullable in the DB; `Number(null)` is `0`, so any product with a null discount shows the 🔥 Sale badge and a struck-through copy of its own price. Verify against API output; fix with `price_after_discount != null && > 0 && < base_price`. Also show the percentage (`-25%`) instead of a generic "Sale".

### B4. 🔴 Mobile bottom nav covers the footer and page bottoms
[MainLayout.tsx](../../resources/js/themes/default/layouts/MainLayout.tsx): spacer is `md-hidden` (typo for `md:hidden`), so it adds 70px on desktop too, and it sits **above** the footer, so on mobile the fixed bottom nav still covers the bottom of the footer. Move the padding to the layout root (`pb-[calc(64px+env(safe-area-inset-bottom))] md:pb-0`). Also, pages render their own `<main>` inside the layout's `<main>` — nested landmarks; make the layout's a `<div>`.

### B5. 🟠 Checkout errors use `alert()`
[Checkout.tsx](../../resources/js/themes/default/pages/Checkout.tsx) has 9 `alert()` calls (missing branch/address/area, server validation joined with commas). Replace with inline field errors + scroll-to-first-error, and a toast for server errors. There is no toast system in the app at all; add `sonner` (shadcn's toast) — it's also needed for B7 and B9.

### B6. 🟠 Checkout summary is misleading
[OrderSummary.tsx](../../resources/js/themes/default/plugins/order-management/components/OrderSummary.tsx):
- **Delivery fee shows "مجاناً / Free"** when no address is selected yet, and also for takeaway orders. Show "اختر العنوان" before selection and hide the row for takeaway.
- **Tax row always shows 0.00** (`amount * 0.0` with a "14% VAT" comment). Hide when 0, or drive from a store setting.
- **COD fee appears only in the summary** — show it next to the "Cash on delivery" option so it isn't a surprise.
- Mobile: summary + "Place order" are at the very bottom of a long form. Add a sticky bottom bar on mobile with total + CTA.

### B7. 🟠 No feedback after "Add to cart"
No toast, no fly-to-cart, no mini-cart. Only the header counter changes (and on mobile it's in the bottom nav). Add a toast with "عرض السلة" action, and show the running total on the product page button ("أضف للسلة · 145 ج.م") which updates as options change.

### B8. 🟠 Mobile menu is one big card per row
`ThemedMenuPage` grid is `grid-cols-1` on mobile with square images, so a 40-item menu is ~40 screens of scrolling. Food-ordering norm (Talabat, Elmenus): compact horizontal rows on mobile (text + price on one side, small image with "+" on the other), grouped by category with a sticky, horizontally scrolling category bar that tracks scroll position. Default sort is `name asc`; it should follow admin `sort_order` / category grouping. The price-range filter is low value for a restaurant menu — consider dropping it on mobile.

### B9. 🟠 Destructive cart actions have no confirm/undo
"Clear cart" in `ThemedCart` fires immediately. Remove item has no undo. Add a confirm dialog for clear-all and an "تراجع" toast for single remove. Replace `window.confirm` in `AddressManager` with the shadcn `AlertDialog`.

### B10. 🟠 Order tracking doesn't update itself
`OrderShow` and `ThemedGuestOrderTracking` fetch once. Add `refetchInterval` (e.g. 20s while status is not completed/cancelled). Guest tracking has no status timeline (registered-user `OrderShow` does) — reuse it. Add "اتصل بالفرع" (`tel:`) and, for completed orders, "اطلب مرة أخرى". Status icon/color helpers are copy-pasted in 3 files; extract one `OrderStatusBadge` + `OrderTimeline`.

### B11. 🟠 Product card isn't keyboard/screen-reader accessible
The whole card is a `div` with `onClick` (no link, no `tabIndex`). Wrap the image + title in a `<Link to=/products/:id>`. The favorite button has no `aria-label`/`aria-pressed`. ~24 icon-only buttons across the theme lack `aria-label` (cart, user menu, mobile menu trigger, footer socials, address edit/delete). `MobileBottomNav` nests `<Button>` inside `<Link>` (button inside anchor) and has no active-tab state or `aria-current`.

### B12. 🟡 Money formatting
Prices use `toFixed(2) + t("currency")` → "120.00 ج.م". Currency comes from the translation file, not the tenant setting, and the Pixel event hardcodes `"EGP"`. Use one `formatMoney()` helper with `Intl.NumberFormat(locale, { style: 'currency', currency: tenant.currency, minimumFractionDigits: 0 })` and drop `.00` for whole amounts.

### B13. 🟡 RTL handled three different ways
Mix of `ltr:mr-2 rtl:ml-2`, `isRTL ? "pr-10" : "pl-10"` ternaries (Login, Register, ForgotPassword, ResetPassword), and physical classes. Zero logical classes (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`) are used. Tailwind supports them natively; migrating removes the duplication and the `isRTL` ternaries. Also set `dir`/`lang` once on `<html>` from i18n instead of per-page `dir=`.

### B14. 🟡 Theme tokens bypassed
~380 hardcoded palette classes (`text-gray-400`, `bg-white`, `text-red-500`, …) vs ~660 token classes. Hardcoded ones ignore tenant brand colors and break in dark mode (only 181 `dark:` variants). Prioritize auth pages and cart/checkout.

### B15. 🟡 Smaller items
- Product description has fixed `h-10`, leaving blank space for short descriptions.
- `HomePage` sets `nameAr: cat.name` (TODO) — Arabic category names are never used even though the API may provide `name_ar`.
- Unavailable products are greyed but still listed in default order; push them to the end of each section.
- Clicking "Add" on a simple product with no `onAddToCart` handler still fetches full product details and opens the options modal — add directly instead.
- Infinite scroll on the menu makes the footer unreachable; acceptable, but make sure contact/branch info is reachable from the header or profile.

---

## Suggested order of work

1. **Storefront bugs first** (small, direct revenue impact): B1, B2, B3, B4.
2. **Admin operator loop:** A2 (new-order alerts), A3 (status tabs), A1 (dashboard) — these matter more day-to-day than most of Phases 2–6.
3. **Checkout trust:** B5, B6, B7 (add toast system once, reuse everywhere).
4. Then Phases 1–7 as planned, folding in A5–A10.
5. Mobile menu redesign (B8) as its own task — it's the biggest storefront change.
