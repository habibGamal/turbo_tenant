# Storefront Theme Contract: Standard `data-testid` Specification

> **Turbo Tenant Multi-Theme Architecture Specification**
> This specification defines the mandatory and standard `data-testid` attributes required for all current and future storefront themes (e.g., `default`, `modern`, `minimal`, `luxury`). 
> 
> Any theme implementing these test identifiers will automatically pass the automated End-to-End (E2E) test suite without needing separate test scripts.

---

## 1. Global Navigation & Header

| Identifier (`data-testid`) | Element Description | Role / Fallback |
|----------------------------|---------------------|-----------------|
| `theme-header` | The top storefront header navigation container | `<header>` |
| `cart-trigger` | Button/link opening the cart drawer or navigating to `/cart` | `button`, `a` |
| `cart-badge-count` | Numerical badge showing current items count in cart | `<span>` |
| `favorites-trigger` | Link navigating to `/favorites` | `a[href="/favorites"]` |
| `favorites-badge-count` | Numerical badge showing count of favorited products | `<span>` |
| `notifications-trigger` | Bell icon triggering notifications popover/sheet | `button` |
| `notifications-badge-count` | Unread notifications badge count | `<span>` |
| `notifications-dropdown` | Notification menu container when opened | `div[role="dialog"]` |
| `mark-all-read-button` | Button inside notification menu to mark all as read | `button` |
| `language-switcher` | Language dropdown / toggle (AR / EN) | `button` |
| `search-input` | Main search bar input field | `<input type="search">` |
| `search-suggestions-container` | Autocompletion results popover card | `<div>` |
| `search-suggestion-item` | Individual search autocomplete suggestion item | `<div>` |

---

## 2. Hero Banners Plugin (`hero-banners`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `hero-banners-slider` | Main hero carousel container on the home page |
| `hero-slide` | Individual hero slide card |
| `hero-slide-cta` | Primary call to action button/link on active slide |
| `hero-next-button` | Carousel next navigation button |
| `hero-prev-button` | Carousel previous navigation button |

---

## 3. Catalog Products Plugin (`catalog-products`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `catalog-section` | Individual section block container on the storefront |
| `section-title` | Title heading of the catalog section |
| `section-view-all` | "View All" link/button navigating to section details |
| `category-card` | Individual category card in category lists |
| `category-tab` | Category filter buttons/tabs on home or catalog page |
| `product-card` | Individual product card in product grids |
| `product-card-title` | Product title text within the card |
| `product-card-price` | Product price text within the card |
| `product-add-to-cart` | "Add to Cart" button on the product card |
| `product-favorite-toggle` | Heart / wishlist toggle button on product card or details |
| `product-details-container`| Root container on `/products/:id` page |
| `product-option-selector` | Radio/select button for variant options (e.g., size, toppings) |
| `product-variant-option` | Radio/label option for selecting a product variant |
| `product-weight-option` | Radio/label option for selecting product weight value |
| `extra-option-checkbox` | Checkbox for toggling an addon/extra option |
| `extra-qty-decrement` | Stepper button decreasing extra option quantity (`-`) |
| `extra-qty-increment` | Stepper button increasing extra option quantity (`+`) |
| `product-detail-add-to-cart` | Primary "Add to Cart" button on product details / modal |
| `product-quantity-input` | Quantity input field on product detail page |
| `product-quantity-decrement` | Stepper button decreasing item quantity (`-`) |
| `product-quantity-increment` | Stepper button increasing item quantity (`+`) |
| `product-quantity-value` | Displayed quantity number indicator |
| `product-options-modal` | Slide-over / dialog modal for configuring options from card |
| `product-not-found` | 404 container when product ID does not exist |
| `browse-menu-button` | Button on 404 page returning to menu/catalog |
| `product-combo-upsell` | "Make it a combo" upsell container section on product details page |
| `product-combo-upsell-item` | Small combo package card linking to package details within upsell section |

---

## 4. Combo Packages Plugin (`combo-packages`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `combos-section` | Container section displaying combo packages |
| `combo-package-card` | Card representing a single combo bundle |
| `combo-package-title` | Title of the combo package |
| `combo-package-price` | Current sell price of the package |
| `combo-package-original-price` | Strikethrough original price when discounted |
| `combo-package-savings` | Chip displaying savings amount / discount |
| `combo-package-contents` | Compact contents summary list of items in the package |
| `combo-package-availability` | Availability window / dayparts badge or reason on combo package card |
| `combo-package-order-type` | Allowed order types badge on combo package card (e.g. delivery only, pickup only) |
| `combo-package-stock` | Stock / daily remaining badge on combo package card (e.g. "Only N left today" or "Sold out today") |
| `combo-unavailable-banner` | Banner inside combo configurator when package is unavailable |
| `combo-add-to-cart` | Button opening combo configurator or adding bundle |
| `combo-configurator` | Modal sheet/dialog or page container for configuring combo package options |
| `combo-group-<groupId>` | Container element for a specific package group |
| `combo-option-<itemId>` | Clickable row/card representing a package item option |
| `combo-variant-<itemId>-<variantId>` | Button for selecting a specific product variant for customer-choice items |
| `combo-qty-inc` | Stepper button increasing package quantity (`+`) |
| `combo-qty-dec` | Stepper button decreasing package quantity (`-`) |
| `combo-notes` | Textarea for customer special instructions or notes |
| `combo-total` | Live total price display of the configured package |
| `combo-submit` | CTA button to add configured package to cart or update in edit mode |
| `combo-group-error-<groupId>` | Inline error message container for group validation failure |
| `combo-component-customize-<itemId>` | Button to expand/collapse customization area (extras & notes) for a component |
| `combo-extra-<itemId>-<extraId>` | Checkbox or toggle for a specific extra on a combo component |
| `combo-extra-qty-inc-<itemId>-<extraId>` | Stepper button to increase quantity of an extra on a combo component |
| `combo-component-notes-<itemId>` | Textarea input for special instructions or notes on a combo component |
| `combo-component-error-<itemId>` | Inline error message container under a component (server or validation error) |

---

## 5. Cart Engine Plugin (`cart-engine`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `cart-drawer` | Slide-over drawer / sheet or cart dialog |
| `cart-page` | Full cart page at `/cart` |
| `cart-item` | Row / card for a single item in the cart |
| `cart-item-title` | Title text of the cart item |
| `cart-item-price` | Price of the cart item |
| `cart-item-variant` | Display container for selected variant details |
| `cart-item-weight-select` | Select trigger for changing item weight inside cart |
| `cart-item-extras` | Display container for item addons/extras |
| `cart-item-qty` | Current quantity indicator for the item |
| `cart-qty-increment` | Button increasing item quantity (`+`) |
| `cart-qty-decrement` | Button decreasing item quantity (`-`) |
| `cart-item-remove` | Button removing item from cart |
| `cart-item-edit` | Button opening the configurator to edit a composite package cart line |
| `cart-item-components` | Container displaying the breakdown of package components |
| `cart-item-component` | Row displaying an individual component of a composite package |
| `cart-item-notes` | Display of customer instructions/notes on a cart item |
| `clear-cart-button` | Button purging all items from cart |
| `cart-subtotal` | Subtotal price display |
| `cart-checkout-button` | Primary CTA navigating to `/checkout` |
| `empty-cart-message` | Message or container displayed when cart has 0 items |
| `start-shopping-button` | Call to action button on empty cart view returning to catalog |

---

## 6. Coupons & Promotions Plugin (`coupons-promotions`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `coupon-form` | Form container for applying promotional codes |
| `coupon-input` | Text input for entering the discount coupon code |
| `coupon-apply-button` | Button submitting the coupon code |
| `coupon-discount-value` | Text displaying the applied discount amount |
| `coupon-remove-button` | Button removing an applied coupon |
| `coupon-error-message` | Error notification or text when coupon is invalid |

---

## 7. Customer Favorites Plugin (`customer-favorites`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `favorites-page` | Root container for `/favorites` page |
| `favorite-item-card` | Product card listed in favorites view |
| `remove-favorite-button` | Button removing item from favorites |
| `empty-favorites-message` | Empty state message when no favorites exist |

---

## 8. Locations & Delivery Plugin (`locations-delivery`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `delivery-zone-select` | Select / dropdown for choosing branch or delivery zone |
| `delivery-address-input` | Street address text input / textarea |
| `delivery-fee-display` | Text element rendering calculated delivery cost |

---

## 9. Guest Checkout Plugin (`guest-checkout`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `checkout-form` | Root checkout form |
| `guest-name-input` | Full name text input for guest customer |
| `guest-phone-input` | Phone number input for guest customer |
| `guest-email-input` | Optional email input |
| `track-order-form` | Form on `/orders/track` |
| `track-order-input` | Order ID / Number input for tracking |
| `track-phone-input` | Phone number verification input for tracking |
| `track-submit-button` | Button submitting tracking lookup |
| `order-status-timeline` | Visual status timeline of tracked order |

---

## 10. Payment Processing Plugin (`payment-processing`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `payment-method-selector` | Container for selecting payment method |
| `payment-cod-radio` | Radio/button for Cash on Delivery |
| `payment-online-radio` | Radio/button for Online Payment (Kashier / Card) |
| `payment-callback-status` | Status message container on callback page |

---

## 11. Order Management Plugin (`order-management`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `place-order-button` | Final button placing the order on checkout |
| `order-summary` | Sticky order summary card displaying totals, fees, and item list |
| `order-confirmation-container` | Confirmation screen after successful order |
| `order-number-display` | Text showing the assigned order number |
| `orders-history-list` | Container listing past customer orders on `/orders` |
| `order-history-item` | Single order summary item in order history |

---

## 12. Product Reviews Plugin (`product-reviews`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `reviews-section` | Container for reviews on `/products/:id` |
| `rating-stars-input` | Interactive star selector (1-5) |
| `review-comment-input` | Textarea for customer review comment |
| `review-submit-button` | Button submitting customer review |
| `review-card` | Individual customer review display item |

---

## 13. CMS Pages Plugin (`cms-pages`)

| Identifier (`data-testid`) | Element Description |
|----------------------------|---------------------|
| `cms-page-container` | Root container on `/pages/:slug` |
| `cms-page-title` | Heading containing CMS page title |
| `cms-page-content` | Container holding sanitized HTML page content |

---

## Implementing in Future Themes

When designing a new theme (e.g. `themes/modern/`):
1. Include the corresponding `data-testid` on your JSX elements:
   ```tsx
   <button data-testid="cart-trigger" onClick={toggleCart}>
     <ShoppingCart />
     <span data-testid="cart-badge-count">{count}</span>
   </button>
   ```
2. Run `npm run test:e2e` to verify your new theme against all 13 plugin test suites!
