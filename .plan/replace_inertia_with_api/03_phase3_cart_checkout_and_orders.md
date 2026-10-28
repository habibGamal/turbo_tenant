# Phase 3: Cart, Checkout & Order Flow

> **Focus:** Migrating cart calculations, delivery selection, guest acquisition gating, order placement, and tracking to standalone REST endpoints and isolated frontend plugin modules.  
> **Target Path:** `.plan/replace_inertia_with_api/03_phase3_cart_checkout_and_orders.md`  

---

## 1. Objectives & Overview

Phase 3 is the operational core of the storefront commerce engine. It decouples the entire shopping and purchasing funnel from Inertia controllers, establishing clear REST boundaries between Cart, Delivery, Guest checkout, and Order management.

Key Plugins Migrated in this Phase:
- `cart-engine` (Carts, items, pricing calculation pipeline, modifiers)
- `locations-delivery` (Branches, governorates, delivery areas, customer addresses)
- `guest-checkout` (Guest user identification, order tracking, authentication gating)
- `order-management` (Order placement pipeline, order status lifecycle, order history)

---

## 2. Backend API Implementation Tasks

### 2.1 CartEngine REST API (`app/Plugins/CartEngine/`)
Create `App\Plugins\CartEngine\src\Http\Controllers\Api\CartApiController`:
- `GET /api/cart` → Returns active cart, items, subtotal, tax, discounts, and total (via `PricingCalculationPipeline`).
- `POST /api/cart/items` → Adds a product or package with chosen variant, extras, and weight.
- `PATCH /api/cart/items/{item}` → Updates quantity.
- `DELETE /api/cart/items/{item}` → Removes item from cart.
- `DELETE /api/cart` → Clears cart.

### 2.2 LocationsDelivery REST API (`app/Plugins/LocationsDelivery/`)
Create `App\Plugins\LocationsDelivery\src\Http\Controllers\Api\DeliveryApiController`:
- `GET /api/branches` → List active store pickup branches.
- `GET /api/governorates` → List active delivery governorates and their areas.
- `GET /api/areas/{area}/fee` → Calculate delivery fee for chosen area.
- `GET /api/addresses` → List saved customer delivery addresses (Auth required).
- `POST /api/addresses` → Save new customer delivery address.

### 2.3 GuestCheckout REST API & Entitlement Gate (`app/Plugins/GuestCheckout/`)
Create `App\Plugins\GuestCheckout\src\Http\Controllers\Api\GuestApiController`:
- `POST /api/guest/session` → Set guest contact details (name, phone, email) in session.
- `GET /api/orders/track/{uuid_or_code}` → Track order progress for guest customers without logging in.
- **Architectural Gate:** If `guest-checkout` is **inactive**, `POST /api/orders` strictly enforces `auth()->check()`. Unauthenticated requests return a `403 Forbidden` with `{ "code": "AUTH_REQUIRED", "message": "Sign in required to checkout" }`.

### 2.4 OrderManagement REST API (`app/Plugins/OrderManagement/`)
Create `App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderApiController`:
- `POST /api/orders` → Executes `OrderValidationPipeline` and `PlaceOrderService`. Returns created order summary and payment redirect info.
- `GET /api/orders` → Paginated list of customer past orders (Auth required).
- `GET /api/orders/{order}` → Full order details, line items, status tracker, and receipt.

---

## 3. Frontend Plugin Directory Organization

### 3.1 `resources/js/plugins/cart-engine/`
```
resources/js/plugins/cart-engine/
├── api/
│   ├── useCart.ts                # TanStack query for /api/cart
│   ├── useAddToCart.ts           # Mutation hook with optimistic cart count update
│   ├── useUpdateCartItem.ts      # Mutation hook
│   └── useRemoveCartItem.ts      # Mutation hook
├── components/
│   ├── CartDrawer.tsx            # Slide-out quick cart drawer
│   ├── CartItemRow.tsx           # Line item with quantity counter & modifiers
│   └── CartTotalsSummary.tsx     # Subtotal, delivery, tax, discount lines
└── types/
    └── index.ts                  # Cart, CartItem, CartModifier interfaces
```

### 3.2 `resources/js/plugins/locations-delivery/`
```
resources/js/plugins/locations-delivery/
├── api/
│   ├── useBranches.ts            # Query for pickup branches
│   ├── useGovernorates.ts        # Query for delivery areas
│   └── useAddresses.ts           # Customer saved address mutations
├── components/
│   ├── DeliveryMethodTabs.tsx    # Delivery vs. Pickup toggle
│   ├── DeliveryAreaSelector.tsx  # Governorate & Area dropdowns
│   └── AddressForm.tsx           # Street, building, floor form
└── types/
    └── index.ts
```

### 3.3 `resources/js/plugins/guest-checkout/`
```
resources/js/plugins/guest-checkout/
├── api/
│   ├── useGuestSession.ts
│   └── useGuestTracking.ts       # Query for order tracking by phone/code
├── components/
│   ├── GuestAccordion.tsx        # Guest checkout personal details accordion
│   └── GuestTrackingCard.tsx     # Tracking timeline
└── types/
    └── index.ts
```

### 3.4 `resources/js/plugins/order-management/`
```
resources/js/plugins/order-management/
├── api/
│   ├── usePlaceOrder.ts          # Mutation for POST /api/orders
│   ├── useOrders.ts              # Query for order history
│   └── useOrder.ts               # Query for order details
├── components/
│   ├── OrderTimeline.tsx         # Pending -> Accepted -> Out for Delivery -> Delivered
│   ├── OrderReceipt.tsx          # Printable order summary
│   └── OrderStatusBadge.tsx      # Colored status pill
└── types/
    └── index.ts
```

---

## 4. Theme Pages Refactoring (Pure React Pages)

### 4.1 `Cart.tsx` (`resources/js/themes/default/pages/Cart.tsx`)
- Driven purely by `useCart()`.
- Incorporates slot for coupons:
  ```tsx
  <PluginSlot name="cart.summary.coupon" />
  ```

### 4.2 `Checkout.tsx` (`resources/js/themes/default/pages/Checkout.tsx`)
- Guarded by `GuestCheckoutGuard`:
  - If user is not authenticated AND `hasPlugin('guest-checkout') === false`, redirect to `/login?redirect=/checkout`.
- Multi-step accordion:
  1. Customer Info (Auth profile or `GuestAccordion`)
  2. Delivery Address (`DeliveryAreaSelector` / `AddressForm`)
  3. Payment Selection (`<PluginSlot name="checkout.payment.methods" />`)
- Submits via `usePlaceOrder()`.

### 4.3 `Track.tsx`, `MyOrders.tsx` & `OrderShow.tsx`
- Standard client-rendered React pages consuming `useGuestTracking()` and `useOrders()`.

---

## 5. Verification & Testing

### 5.1 Automated Backend Tests
- `tests/Feature/Api/CartApiTest.php`: Assert item addition, pricing pipeline calculation, item deletion.
- `tests/Feature/Api/OrderApiTest.php`: Test full order placement cycle, guest gating enforcement, order details retrieval.

### 5.2 Manual Browser Verification
- Add items with extras/weights from ProductShow → Verify cart drawer badge increments.
- Proceed to Checkout as guest (with `guest-checkout` enabled) → Complete order.
- Disable `guest-checkout` for tenant → Attempt unauthenticated checkout → Verify immediate redirect to `/login`.
