# Phase 4: Customer Engagement & Addons

> **Focus:** Migrating reviews, promotional coupons, combo meal packages, and push notifications to autonomous REST APIs, headless hooks, default fallbacks, and theme overrides.  
> **Target Path:** `.plan/replace_inertia_with_api/04_phase4_customer_engagement_and_addons.md`  

---

## 1. Objectives & Overview

Phase 4 validates the true power of the decoupled multi-theme architecture:
- Each addon exposes its own REST API.
- Each addon provides **Headless Hooks** (`useReviews`, `useApplyCoupon`, `useCombos`) and a **Default Fallback Component**.
- The active theme (`themes/{themeName}/plugins/`) can supply custom presentation overrides that seamlessly integrate with the theme's typography, colors, and layout.
- The `<PluginSlot />` engine automatically resolves the theme's custom component, falls back to the plugin default if uncustomized, or cleanly unmounts if the plugin is inactive.

Key Plugins Migrated in this Phase:
- `product-reviews` (Customer star ratings, review submission, image attachments)
- `coupons-promotions` (Discount codes, validation rules, cart discount pipeline)
- `combo-packages` (Group meals, multi-item bundles)
- `push-notifications` (Expo push token registration and customer alerts)

---

## 2. Backend API Implementation Tasks

### 2.1 ProductReviews REST API (`app/Plugins/ProductReviews/`)
Ensure `App\Plugins\ProductReviews\src\Http\Controllers\ReviewController`:
- `GET /api/products/{product}/reviews` → Paginated list of published reviews, user names, ratings, comments, and image URLs.
- `POST /api/products/{product}/reviews` → Handles validated review submission with multipart image uploads. Returns `201 Created` with created review payload.

### 2.2 CouponsPromotions REST API (`app/Plugins/CouponsPromotions/`)
Create `App\Plugins\CouponsPromotions\src\Http\Controllers\Api\CouponApiController`:
- `POST /api/coupons/apply` → Validates promo code against tenant rules (minimum cart value, expiry, customer usage limits). Attaches code to cart and recalculates totals via `PricingCalculationPipeline`.
- `POST /api/coupons/remove` → Clears applied coupon and updates cart.

### 2.3 ComboPackages REST API (`app/Plugins/ComboPackages/`)
Create `App\Plugins\ComboPackages\src\Http\Controllers\Api\ComboApiController`:
- `GET /api/combos` → List active package bundles with group selections.
- `GET /api/combos/{package}` → Bundle details with item choices.

### 2.4 PushNotifications REST API (`app/Plugins/PushNotifications/`)
Create `App\Plugins\PushNotifications\src\Http\Controllers\Api\PushNotificationApiController`:
- `POST /api/notifications/register-token` → Stores customer Expo push token associated with tenant and user/device.

---

## 3. Frontend: Headless Plugins vs. Theme Overrides

### 3.1 Headless Plugins (`resources/js/plugins/`)

#### Product Reviews (`resources/js/plugins/product-reviews/`)
```
resources/js/plugins/product-reviews/
├── api/
│   ├── useReviews.ts             # Headless query
│   └── useSubmitReview.ts        # Headless mutation
├── components/
│   ├── DefaultReviewsSection.tsx # Fallback UI
│   ├── DefaultReviewForm.tsx
│   └── StarRating.tsx            # Headless star math & display
└── index.ts                      # registerPluginDefault('product.tabs', DefaultReviewsSection)
```

#### Coupons & Promotions (`resources/js/plugins/coupons-promotions/`)
```
resources/js/plugins/coupons-promotions/
├── api/
│   ├── useApplyCoupon.ts         # Headless mutation (calls API & invalidates cart)
│   └── useRemoveCoupon.ts        # Headless mutation
├── components/
│   ├── DefaultCouponInput.tsx    # Fallback UI
│   └── DefaultCouponBadge.tsx
└── index.ts                      # registerPluginDefault('cart.summary.coupon', DefaultCouponInput)
```

#### Combo Packages (`resources/js/plugins/combo-packages/`)
```
resources/js/plugins/combo-packages/
├── api/
│   ├── useCombos.ts              # Headless query
│   └── useCombo.ts
├── components/
│   └── DefaultComboCard.tsx      # Fallback UI
└── index.ts                      # registerPluginDefault('product.upsell', DefaultComboCard)
```

---

### 3.2 Theme Overrides (`resources/js/themes/default/plugins/`)

The `default` theme customizes the appearance of these plugins to match its specific design system:

```
resources/js/themes/default/plugins/
├── product-reviews/
│   ├── ThemedReviewsSection.tsx  # Styled with theme cards, custom font, and modal
│   └── index.ts                  # registerThemeOverride('default', 'product.tabs', ThemedReviewsSection)
│
├── coupons-promotions/
│   ├── ThemedCouponBox.tsx       # Pill-style input box with themed outline & button
│   └── index.ts                  # registerThemeOverride('default', 'cart.summary.coupon', ThemedCouponBox)
│
└── combo-packages/
    ├── ThemedComboMealCard.tsx   # Badge highlighting, quantity selector
    └── index.ts                  # registerThemeOverride('default', 'product.upsell', ThemedComboMealCard)
```

---

## 4. Slot Resolution in Storefront Pages

The theme pages simply declare the slots without caring which theme component or fallback renders:

```tsx
// resources/js/themes/default/pages/ProductShowPage.tsx
<div className="product-details">
  <ProductInfo product={product} />
  <ProductOptions product={product} />

  {/* Resolves ThemedReviewsSection -> DefaultReviewsSection -> null */}
  <PluginSlot 
    name="product.tabs" 
    pluginId="product-reviews" 
    context={{ productId: product.id }} 
  />

  {/* Resolves ThemedComboMealCard -> DefaultComboCard -> null */}
  <PluginSlot 
    name="product.upsell" 
    pluginId="combo-packages" 
    context={{ product }} 
  />
</div>
```

---

## 5. Verification & Testing

### 5.1 Automated Backend Tests
- `tests/Feature/Api/ReviewApiTest.php`: Submit review, assert JSON 201, fetch reviews assert JSON pagination.
- `tests/Feature/Api/CouponApiTest.php`: Apply valid promo code assert cart discount applied, apply invalid code assert 422 JSON error.

### 5.2 Multi-Theme Slot Verification
1. **Themed Look:** In `default` theme, product reviews render with `ThemedReviewsSection.tsx`.
2. **Fallback Look:** Switch to a bare theme without review overrides → `DefaultReviewsSection.tsx` renders seamlessly.
3. **Deactivation:** Disable `product-reviews` plugin → Slot returns `null`, zero layout shift or errors.
