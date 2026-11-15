# Live-Only E2E Test Suite for Frontend React, Plugins & Multi-Theme Architecture

## Goal Description
Build a production-grade, high-speed End-to-End (E2E) test suite targeting all React storefront pages and all 13 decoupled plugins in **Live Mode Only**. 

All test scenarios execute pure end-to-end user journeys through the browser UI against the live Laravel multi-tenant backend (`http://kofe.localhost:8000`) and MySQL database—without mock data or API shortcuts.

The architecture enforces a strict **Theme Contract Specification** using standardized `data-testid` attributes documented in `docs/THEME_TEST_IDS_SPEC.md`. Any current theme (`default`) or future themes (`modern`, `minimal`, etc.) adhering to this specification will automatically be 100% verified by the exact same test suite.

```
+---------------------------------------------------------------------------------------------------+
|                                   PLAYWRIGHT LIVE TEST RUNNER                                     |
|    (Chromium-1187, Fully Parallel Workers, Auto-Wait, Host Resolver Rules: *.localhost -> 127.0.0.1) |
+---------------------------------------------------------------------------------------------------+
                                                  │
                                                  ▼
                  +───────────────────────────────────────────────────────────────+
                  |               STANDARDIZED THEME TEST ID SPEC                 |
                  |                docs/THEME_TEST_IDS_SPEC.md                    |
                  |  Standard data-testid contract mandatory for all themes       |
                  +───────────────────────────────────────────────────────────────+
                                                  │
                                                  ▼
                  +───────────────────────────────────────────────────────────────+
                  |                 THEME ADAPTER LAYER (POM)                     |
                  |  Decouples plugin functional logic from visual theme layouts  |
                  +───────────────────────────────────────────────────────────────+
                                   │                              │
                     ┌─────────────┴─────────────┐                │
                     ▼                           ▼                ▼
            +─────────────────+         +─────────────────+    +──────────────────────+
            |  Default Theme  |         |  Future Theme   |    |  Future Theme        |
            |    (Current)    |         |  (e.g., Modern) |    |  (e.g., Minimal)     |
            +─────────────────+         +─────────────────+    +──────────────────────+
                                                  │
                                                  ▼
                  +───────────────────────────────────────────────────────────────+
                  |               HIGH-SPEED LIVE PURE-UI EXECUTION               |
                  |  • Real user actions (click, type, navigate)                  |
                  |  • Route Blocking: Blocks 3rd-party pixels/fonts for speed    |
                  |  • Isolated Sessions: Unique worker guest states & carts      |
                  +───────────────────────────────────────────────────────────────+
                                                  │
                                                  ▼
                  +───────────────────────────────────────────────────────────────+
                  |           13 LIVE PLUGIN SPECS (Targeting Real Backend)       |
                  |  1. Catalog Products         8. Locations & Delivery          |
                  |  2. Cart Engine              9. Order Management              |
                  |  3. Coupons & Promotions    10. Payment Processing            |
                  |  4. Customer Favorites      11. Product Reviews               |
                  |  5. Guest Checkout          12. Push Notifications            |
                  |  6. Hero Banners            13. CMS Pages                     |
                  |  7. Combo Packages                                            |
                  +───────────────────────────────────────────────────────────────+
                                                  │
                                                  ▼
                                    [ LIVE LARAVEL BACKEND ]
                            http://kofe.localhost:8000 + MySQL DB
```

---

## Standardized Theme Test IDs (`docs/THEME_TEST_IDS_SPEC.md`)

A formal specification in `docs/` defines all standard element identifiers required across themes:
- **Navigation & Header**: `cart-trigger`, `cart-badge-count`, `favorites-trigger`, `favorites-badge-count`, `notifications-trigger`, `notifications-badge-count`.
- **Catalog & Products**: `product-card`, `product-card-title`, `product-card-price`, `product-add-to-cart`, `product-favorite-toggle`, `product-option-selector`.
- **Cart Engine**: `cart-drawer`, `cart-page`, `cart-item`, `cart-qty-increment`, `cart-qty-decrement`, `cart-item-remove`, `cart-subtotal`, `cart-checkout-button`, `empty-cart-message`.
- **Checkout & Guest**: `checkout-form`, `guest-name-input`, `guest-phone-input`, `delivery-zone-select`, `delivery-address-input`, `payment-cod-radio`, `payment-online-radio`, `place-order-button`, `order-confirmation-container`.
- **Coupons**: `coupon-form`, `coupon-input`, `coupon-apply-button`, `coupon-discount-value`, `coupon-error-message`.
- **Hero & Combos**: `hero-banners-slider`, `hero-slide`, `hero-slide-cta`, `combos-section`, `combo-package-card`, `combo-add-to-cart`.
- **Order Tracking & History**: `track-order-form`, `track-order-input`, `track-phone-input`, `track-submit-button`, `order-status-timeline`, `orders-history-list`.
- **Reviews & Notifications**: `reviews-section`, `review-submit-button`, `rating-stars-input`, `review-comment-input`, `notifications-dropdown`, `mark-all-read-button`.
- **CMS**: `cms-page-container`, `cms-page-title`, `cms-page-content`.

---

## File Structure

```
tests/e2e/
├── config/
│   └── playwright.config.ts
├── helpers/
│   ├── live-fixture.ts
│   └── tracker-blocker.ts
├── themes/
│   ├── theme.contract.ts
│   ├── theme-factory.ts
│   ├── base/
│   │   ├── BaseThemeAdapter.ts
│   │   ├── CartAdapter.ts
│   │   ├── CatalogAdapter.ts
│   │   ├── CheckoutAdapter.ts
│   │   └── ...
│   └── default/
│       └── index.ts
└── plugins/
    ├── catalog-products.live.spec.ts
    ├── cart-engine.live.spec.ts
    ├── coupons-promotions.live.spec.ts
    ├── customer-favorites.live.spec.ts
    ├── guest-checkout.live.spec.ts
    ├── hero-banners.live.spec.ts
    ├── combo-packages.live.spec.ts
    ├── locations-delivery.live.spec.ts
    ├── order-management.live.spec.ts
    ├── payment-processing.live.spec.ts
    ├── product-reviews.live.spec.ts
    ├── push-notifications.live.spec.ts
    └── cms-pages.live.spec.ts
```

---

## Verification Plan
1. `npm install -D @playwright/test` (with network auto-retry)
2. `npx tsc --noEmit`
3. `npx playwright test` (executes all 13 plugin test specs against live backend)
4. `npx playwright test tests/e2e/plugins/cart-engine.live.spec.ts`
