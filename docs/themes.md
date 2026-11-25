# Storefront themes and test IDs

The storefront supports multiple React themes over a shared core, slot overrides, dynamic tenant CSS variables, and a standardized `data-testid` contract for Playwright E2E tests.

**Code:** [`resources/js/themes/registry.ts`](../resources/js/themes/registry.ts), [`resources/js/themes/default`](../resources/js/themes/default/index.ts), [`resources/js/core/slots`](../resources/js/core/slots/slot-registry.ts), [`ThemeCssBuilder`](../app/Plugins/StoreSettings/src/Theme/ThemeCssBuilder.php), [`theme.contract.ts`](../tests/e2e/themes/theme.contract.ts)

## Theme architecture

1. **Theme registration:** [`themes/registry.ts`](../resources/js/themes/registry.ts) manages registered themes (`registerTheme`, `resolveTheme`, `getRegisteredThemes`). If a tenant's configured theme name is missing or unregistered, `resolveTheme` falls back to `default`.
2. **Layouts, pages, and plugins:** each [`ThemeDefinition`](../resources/js/themes/registry.ts#L119) supplies `layouts` (`MainLayout`), `pages` (`Home`, `Checkout`, `OrderShow`, `Login`, `Register`, `ForgotPassword`, `ResetPassword`, `Profile`, `NotFound`), optional `plugins` page components (`ThemedCmsPages`, `ThemedCustomerFavorites`, `ThemedCart`, `ThemedGuestOrderTracking`, `ThemedOrdersList`, `ThemedPaymentCallback`, `ThemedKashierPayment`, `ThemedProductShow`, `ThemedMenuPage`), and `registerOverrides()`.
3. **Slot overrides:** plugins register defaults via `registerPluginDefault` in [`slot-registry.ts`](../resources/js/core/slots/slot-registry.ts); themes override slots via `registerThemeOverride` inside `registerOverrides()`, and pages render `<PluginSlot name="..." />`.
4. **Dynamic tenant CSS variables:** [`ThemeCssBuilder`](../app/Plugins/StoreSettings/src/Theme/ThemeCssBuilder.php) validates hex colors and radius tokens from the store's `theme` setting (`ThemePresets`) and inlines `:root` CSS variables (`--primary`, `--primary-foreground`, `--ring`, `--secondary`, `--secondary-foreground`, `--accent`, `--accent-foreground`, `--radius`) into [app.blade.php](../resources/views/app.blade.php).
5. **Shared UI components:** themes share Shadcn / Radix primitives under `resources/js/components/ui/`.

### Adding a theme

1. Create `resources/js/themes/<name>/` with `layouts/`, `pages/`, `plugins/`, and `index.ts` exporting a `ThemeDefinition`.
2. Register the theme via `registerTheme(...)` at app startup (`resources/js/app.tsx`).
3. Implement all standard `data-testid` attributes below (and an adapter if needed in `tests/e2e/themes/`) so `npm run test:e2e` passes against the theme.

## Standard `data-testid` contract

Every theme implements these `data-testid` attributes so the E2E suite runs across themes without rewriting specs.

### Global navigation and header

| `data-testid` | Element |
| --- | --- |
| `theme-header` | Top storefront header container |
| `cart-trigger` | Button or link opening cart drawer / `/cart` |
| `cart-badge-count` | Cart items count badge |
| `favorites-trigger` | Link to `/favorites` |
| `favorites-badge-count` | Favorited products count badge |
| `notifications-trigger` | Notifications button |
| `notifications-badge-count` | Unread notifications count badge |
| `notifications-dropdown` | Notifications menu container |
| `mark-all-read-button` | Mark all notifications read button |
| `language-switcher` | Language toggle (AR / EN) |
| `search-input` | Search input field |
| `search-suggestions-container` | Search autocomplete popover |
| `search-suggestion-item` | Single search suggestion item |

### Hero banners (`hero-banners`)

| `data-testid` | Element |
| --- | --- |
| `hero-banners-slider` | Home hero carousel container |
| `hero-slide` | Single hero slide |
| `hero-slide-cta` | Primary CTA on slide |
| `hero-next-button` | Next slide button |
| `hero-prev-button` | Previous slide button |

### Catalog products (`catalog-products`)

| `data-testid` | Element |
| --- | --- |
| `catalog-section` | Section container |
| `section-title` | Section heading |
| `section-view-all` | View all link/button |
| `category-card` | Category card |
| `category-tab` | Category filter tab/button |
| `product-card` | Product card |
| `product-card-title` | Product title in card |
| `product-card-price` | Product price in card |
| `product-add-to-cart` | Add-to-cart button on card |
| `product-favorite-toggle` | Favorite heart toggle |
| `product-details-container` | Root container on `/products/:id` |
| `product-option-selector` | Option selector container |
| `product-variant-option` | Variant option selector |
| `product-weight-option` | Weight option selector |
| `extra-option-checkbox` | Extra option checkbox |
| `extra-qty-decrement` / `extra-qty-increment` | Extra quantity steppers |
| `product-detail-add-to-cart` | Primary add-to-cart button on PDP/modal |
| `product-quantity-input`, `product-quantity-decrement`, `product-quantity-increment`, `product-quantity-value` | Product quantity controls |
| `product-options-modal` | Options configuration modal |
| `product-not-found`, `browse-menu-button` | Missing product state and CTA |
| `product-combo-upsell`, `product-combo-upsell-item` | Product page combo upsell list and cards |

### Combo packages (`combo-packages`)

| `data-testid` | Element |
| --- | --- |
| `combos-section`, `combo-package-card` | Combo section and package card |
| `combo-package-title`, `combo-package-price`, `combo-package-original-price`, `combo-package-savings` | Card title, prices, and savings |
| `combo-package-contents`, `combo-package-availability`, `combo-package-order-type`, `combo-package-stock` | Contents summary and sales-rule badges |
| `combo-unavailable-banner` | Banner when package is outside hours or sold out |
| `combo-add-to-cart`, `combo-configurator` | Open configurator button and configurator root |
| `combo-group-<groupId>`, `combo-option-<itemId>`, `combo-variant-<itemId>-<variantId>` | Group, option, and customer-choice variant selectors |
| `combo-qty-inc`, `combo-qty-dec`, `combo-notes`, `combo-total`, `combo-submit` | Quantity, notes, live total, and submit CTA |
| `combo-group-error-<groupId>`, `combo-component-error-<itemId>` | Inline group and component errors |
| `combo-component-customize-<itemId>`, `combo-extra-<itemId>-<extraId>`, `combo-extra-qty-inc-<itemId>-<extraId>`, `combo-component-notes-<itemId>` | Component extras and per-component notes |

### Cart (`cart-engine`) and coupons (`coupons-promotions`)

| `data-testid` | Element |
| --- | --- |
| `cart-drawer`, `cart-page` | Cart drawer and `/cart` page |
| `cart-item`, `cart-item-title`, `cart-item-price`, `cart-item-variant`, `cart-item-weight-select`, `cart-item-extras`, `cart-item-qty` | Cart line details |
| `cart-qty-increment`, `cart-qty-decrement`, `cart-item-remove`, `cart-item-edit` | Cart line actions |
| `cart-item-components`, `cart-item-component`, `cart-item-notes` | Combo components and notes |
| `clear-cart-button`, `cart-subtotal`, `cart-checkout-button`, `empty-cart-message`, `start-shopping-button` | Cart summary and empty state |
| `coupon-form`, `coupon-input`, `coupon-apply-button`, `coupon-discount-value`, `coupon-remove-button`, `coupon-error-message` | Coupon input and discount feedback |

### Checkout, orders, guest tracking, payments, reviews, favorites, CMS

| Plugin | `data-testid` values |
| --- | --- |
| `locations-delivery` | `delivery-zone-select`, `delivery-address-input`, `delivery-fee-display` |
| `guest-checkout` | `checkout-form`, `guest-name-input`, `guest-phone-input`, `guest-email-input`, `track-order-form`, `track-order-input`, `track-phone-input`, `track-submit-button`, `order-status-timeline` |
| `payment-processing` | `payment-method-selector`, `payment-cod-radio`, `payment-online-radio`, `payment-callback-status` |
| `order-management` | `place-order-button`, `order-summary`, `order-confirmation-container`, `order-number-display`, `orders-history-list`, `order-history-item` |
| `customer-favorites` | `favorites-page`, `favorite-item-card`, `remove-favorite-button`, `empty-favorites-message` |
| `product-reviews` | `reviews-section`, `rating-stars-input`, `review-comment-input`, `review-submit-button`, `review-card` |
| `cms-pages` | `cms-page-container`, `cms-page-title`, `cms-page-content` |
