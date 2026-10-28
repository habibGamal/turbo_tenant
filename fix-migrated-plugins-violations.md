# Fix Migrated Plugins Violations Plan

> Objective: Remediate all architectural, routing, slot, and Inertia-coupling violations across migrated plugins. Drop backward compatibility in favor of a 100% decoupled REST API and Headless React SPA architecture with consolidated theme plugin folders.

---

## Phase 1: Backend Cleanup & Route Purging (Drop Backward Compatibility)
- [x] **app/Plugins/CartEngine/routes/tenant.php**: Remove legacy `Route::get('/cart', ...)` Inertia closure and legacy route aliases. Keep pure `/api/cart` endpoints.
- [x] **app/Plugins/CmsPages**: Remove legacy `/pages/{slug}` Inertia route from `routes/tenant.php`; delete dead `PageController.php`.
- [x] **app/Plugins/CustomerFavorites**: Remove legacy `/favorites` Inertia route from `routes/tenant.php`; delete dead `FavoritesController.php`.
- [x] **app/Plugins/GuestCheckout**: Remove legacy web `/track-order` and `/orders/track` routes from `routes/tenant.php`; delete dead `GuestOrderTrackingController.php`.
- [x] **app/Plugins/OrderManagement/routes/tenant.php**: Remove legacy Inertia web routes (`/checkout`, `/orders`, `/orders/{orderId}`). Keep pure `/api/...` endpoints.
- [x] **app/Plugins/PaymentProcessing/src/Services/PaymobService.php**: Fix undefined property `$this->settingService` (use `$config->paymobCurrency ?? 'EGP'`).
- [x] **tests**: Update tests that asserted `assertInertia` on removed legacy web routes to assert API endpoints.

## Phase 2: Slot Registry & Core Foundation Fixes
- [x] **resources/js/core/slots/slot-registry.ts**: Add `'cart.page'`, `'header.actions.cart'`, `'favorites.page'`, `'product.card.favorite'`, `'cms.page'`, `'customer.orders'` to `SlotName` type union.
- [x] **resources/js/plugins/customer-favorites/index.ts**: Remove `registerPluginDefault('home.sections.after', ...)` to eliminate collision with `catalog-products`.
- [x] **resources/js/plugins/guest-checkout/index.ts**: Register `DefaultGuestCheckout` for `'checkout.guest.form'`.
- [x] **index.ts files**: Remove top-level auto-invocations in `resources/js/plugins/order-management/index.ts`, `resources/js/themes/default/plugins/order-management/index.ts`, and `resources/js/themes/default/plugins/guest-checkout/index.ts`.
- [x] **resources/js/utils/cartUtils.ts**: Replace `import axios from 'axios'` with `apiClient` from `@/core/api/client`.

## Phase 3: Theme Component Relocation & Organization
- [x] **cart-engine**: Move `CartItem.tsx`, `CartSummary.tsx`, `EmptyCart.tsx` into `resources/js/themes/default/plugins/cart-engine/components/`.
- [x] **hero-banners**: Move `HeroSlider.tsx` into `resources/js/themes/default/plugins/hero-banners/components/`.
- [x] **catalog-products**: Move `ProductCard.tsx`, `ProductsSection.tsx`, `CategoriesSection.tsx`, and `product/*` subcomponents into `resources/js/themes/default/plugins/catalog-products/components/`.
- [x] **guest-checkout**: Move `GuestInformationForm.tsx` into `resources/js/themes/default/plugins/guest-checkout/components/`.
- [x] **locations-delivery**: Move `DeliveryAddressSection.tsx` and `AddressManager.tsx` into `resources/js/themes/default/plugins/locations-delivery/components/`.
- [x] **order-management**: Move `OrderSummary.tsx`, `OrderTypeSelection.tsx`, `BranchSelection.tsx`, `PaymentMethodSelection.tsx`, `OrderNotesSection.tsx` into `resources/js/themes/default/plugins/order-management/components/`.

## Phase 4: Purge `@inertiajs/react` Coupling & Adopt `react-router-dom`
- [x] Replace `import { Link } from "@inertiajs/react"` with `import { Link } from "react-router-dom"` (`<Link to="...">`), removing Ziggy `route()` dependencies in:
  - `resources/js/themes/default/plugins/cart-engine/ThemedCart.tsx`
  - `resources/js/themes/default/plugins/cart-engine/ThemedCartButton.tsx`
  - `resources/js/themes/default/plugins/order-management/ThemedOrdersList.tsx`
  - `resources/js/themes/default/plugins/hero-banners/components/HeroSlider.tsx`
  - `resources/js/themes/default/plugins/catalog-products/components/ProductCard.tsx` (replace `router.visit` with `useNavigate` or `<Link to="...">`).
- [x] Streamline `ThemedProductDetails.tsx` to render meaningful auxiliary details rather than duplicating main product view.

## Phase 5: Wire Slots in Storefront Pages & Verification
- [x] Update `resources/js/themes/default/pages/Checkout.tsx` to use `<PluginSlot>` for guest checkout, address selection, etc.
- [x] Update imports across pages (`HomePage.tsx`, `Cart.tsx`, `Favorites.tsx`, `ProductShow.tsx`, `MyOrders.tsx`, `Profile.tsx`) to reference the relocated theme plugin components.
- [x] Verify TypeScript: `npx tsc --noEmit`.
- [x] Verify Backend Tests: `php artisan test`.
