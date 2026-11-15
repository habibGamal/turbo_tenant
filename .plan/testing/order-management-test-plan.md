# Detailed Implementation & Test Plan: OrderManagement Live E2E Test Suite

## Goal Description
Establish an exhaustive, decoupled, live-only End-to-End (E2E) testing framework for the `order-management` plugin in Turbo Tenant. The test suite verifies the complete order lifecycle across the headless React storefront, multi-tenant database persistence (`kofe` tenant), and REST APIs.

Following the universal invariants of `@[skills/e2e-test]`, all tests run in strict live mode against real backend state with decoupled theme adapters (`data-testid`), zero mock handlers, single-worker execution (`workers: 1`), and deterministic response synchronization.

```
+──────────────────────────────────────────────────────────────────────────────────────────────────────────+
|                                ORDER-MANAGEMENT COMPREHENSIVE TEST MATRIX                                |
+──────────────────────────────────────────────────────────────────────────────────────────────────────────+
   │
   ├── 01. CHECKOUT DATA BOOTSTRAP & FORM INITIALIZATION
   │   ├── 1.1 Fetch checkout bootstrap data (/api/checkout-data) -> cart, branches, governorates
   │   ├── 1.2 Form auto-selects first active branch and defaults payment method to COD
   │   └── 1.3 Guest detection: Renders guest information form fields when unauthenticated
   │
   ├── 02. GUEST CASH ON DELIVERY (COD) ORDER PLACEMENT
   │   ├── 2.1 Complete delivery checkout form with guest details (name, phone, area, address)
   │   ├── 2.2 Submit order -> POST /api/orders/place returns success (200), order_id, and order_number
   │   ├── 2.3 Post-order side effects: Cart session cleared, redirects to order tracking / confirmation
   │   └── 2.4 Database persistence: Verify order and order_items stored under guest record in tenant DB
   │
   ├── 03. GUEST TAKEAWAY (STORE PICKUP) ORDER PLACEMENT
   │   ├── 3.1 Toggle order type to "Takeaway" (web_takeaway)
   │   ├── 3.2 Verify delivery address form is dismissed & delivery fee recalculates to 0 EGP
   │   └── 3.3 Submit takeaway order -> verify order record has type=web_takeaway and 0 delivery fee
   │
   ├── 04. AUTHENTICATED CUSTOMER ORDER PLACEMENT
   │   ├── 4.1 Login as registered tenant customer with pre-configured addresses
   │   ├── 4.2 Auto-selection of customer default delivery address & elimination of guest inputs
   │   └── 4.3 Submit authenticated order -> verify order is directly associated with user_id
   │
   ├── 05. COMPLEX CART ORDER PERSISTENCE (Variants, Weights & Extras)
   │   ├── 5.1 Add multi-configured product (Simple Coffee: Variant "سادة" + 250g weight + "Gensing" extra)
   │   ├── 5.2 Place order via checkout
   │   └── 5.3 Verify database order_items records preserve variant_name, weight_multiplier, and order_item_extras
   │
   ├── 06. CHECKOUT PRICING & FEE COMPUTATIONS
   │   ├── 6.1 Subtotal math: Sum of cart item prices matches line items
   │   ├── 6.2 Zone-based delivery fee computation: Selecting Cairo/Giza area updates delivery fee dynamically
   │   ├── 6.3 Cash On Delivery (COD) service fee calculation (when configured in settings)
   │   ├── 6.4 Coupon discount subtraction from final order total
   │   └── 6.5 Final total equality: total = subtotal + delivery_fee + cod_fee + tax - discount
   │
   ├── 07. VALIDATION SAFEGUARDS & BOUNDARY CONDITIONS
   │   ├── 7.1 Empty cart boundary: Visiting /checkout with 0 items displays EmptyCart notice and blocks placement
   │   ├── 7.2 Missing guest required fields: Submitting without name or phone triggers validation alert
   │   ├── 7.3 Missing delivery area: Submitting delivery order without area triggers validation alert
   │   └── 7.4 Backend 422 error handling: Corrupt payload gracefully alerts user without crashing SPA
   │
   ├── 08. CUSTOMER ORDER HISTORY LIST (/orders)
   │   ├── 8.1 Navigate to /orders as authenticated user -> displays paginated order history cards
   │   ├── 8.2 Order cards display order number, timestamp, branch, payment method, and total
   │   ├── 8.3 Order status badges render with proper semantic colors (pending, processing, delivered, cancelled)
   │   ├── 8.4 Empty state: Authenticated user with no orders sees "No orders yet" and "Start Shopping" CTA
   │   └── 8.5 Pagination: Next/previous pagination navigates pages and fetches new records
   │
   ├── 09. ORDER DETAILS INSPECTION (/orders/:id)
   │   ├── 9.1 Clicking "View Details" on order card routes to /orders/:id
   │   ├── 9.2 Displays complete order summary, items list with variant/weight/extras tags, subtotal, and total
   │   ├── 9.3 Displays delivery address details and customer note if present
   │   └── 9.4 404 fallback: Invalid order ID shows "Order not found" notice with return link
   │
   └── 10. ORDER STATUS LIFECYCLE & POS SYNC
       ├── 10.1 Trigger status transition via POST /api/order-status (pending -> processing -> delivered)
       ├── 10.2 Verify database order status updates to newly assigned status
       └── 10.3 Verify UI (Order Details or Order Tracking) reflects updated status tag/timeline
```

---

## Codebase Audit & Technical Discoveries

### 1. Existing Infrastructure & Endpoints
- **API Endpoints (`app/Plugins/OrderManagement/routes/tenant.php`)**:
  - `GET /api/checkout-data`: Returns `{ cart, addresses, branches, governorates, is_guest }`.
  - `POST /api/orders/place`: Accepts `PlaceOrderPayload` (`branch_id`, `payment_method`, `type`, `guest_data` or `address_id`, `note`, `coupon_id`).
  - `GET /api/orders` (Auth): Returns paginated orders for the logged-in customer.
  - `GET /api/orders/{orderId}` (Auth): Returns single order with relations (`items.extras`, `branch`, `address.area`).
  - `POST /api/order-status`: Public webhook/API to update order status by `orderNumber` and `status`.

### 2. Frontend Components & Router Findings
- **Checkout Page (`resources/js/themes/default/pages/Checkout.tsx`)**:
  - Contains `data-testid="checkout-form"` and `OrderSummary` with `data-testid="order-summary"` and `data-testid="place-order-button"`.
  - Form components: `OrderTypeSelection`, `BranchSelection`, `GuestInformationForm`, `DeliveryAddressSection`, `PaymentMethodSelection`, `OrderNotesSection`, `CouponCodeSection`.
- **Order History (`resources/js/themes/default/plugins/order-management/ThemedOrdersList.tsx`)**:
  - Already registered in slot `customer.orders` and routed at `/orders`.
  - Needs standard `data-testid` attributes (`orders-history-list`, `order-history-item`, `order-number`, `order-status`, `order-total`, `view-order-details`).
- **Order Details (`resources/js/themes/default/pages/OrderShow.tsx`)**:
  - Fully implemented component with items breakdown, extras, weights, status icons, and address.
  - **CRITICAL GAP FOUND**: In `resources/js/core/router/AppRouter.tsx`, the route `/orders/:id` currently renders `<PlaceholderPage title="Order Details" />` instead of `<ThemedOrderShow />`!
- **OrderController Redirect URL Finding**:
  - In `OrderController::placeOrder`, for COD orders:
    ```php
    'redirect_url' => route('orders.show', ['orderId' => $order->id])
    ```
    However, route named `orders.show` is NOT registered in Laravel (only `api.orders.show` exists), and in SPA mode `redirect_url` should be the client path `/orders/{id}` or `/orders/track?order_number=...`. If `route('orders.show')` is called, Laravel throws a `RouteNotFoundException` causing a 500 error! This needs to be cleanly handled for SPA compatibility.

---

## User Review Required

> [!IMPORTANT]
> **Key Architectural Decisions for User Approval:**
> 1. **Client Route Resolution in OrderController**:
>    Change backend redirect URL in `OrderController.php` from `route('orders.show', ...)` (which doesn't exist and throws 500) to client SPA path:
>    - For Guest COD: `/orders/track?order_number={$order->order_number}&phone={$user->phone}`
>    - For Auth COD: `/orders/{$order->id}`
>    This enables seamless React router navigation (`navigate(redirect_url)` in `Checkout.tsx`).
> 2. **Connect `OrderShow` to `AppRouter.tsx`**:
>    Replace `<Route path="/orders/:id" element={<PlaceholderPage title="Order Details" />} />` with `<ThemedOrderShow />` (or `OrderShowRoute`) so order details pages render live order data.
> 3. **Extend Theme Contract (`IOrderTheme`)**:
>    Add `IOrderTheme` to `tests/e2e/themes/theme.contract.ts` and `BaseThemeAdapter.ts` with getters for checkout form elements, order history cards, order details, and status badges.
> 4. **Authentication Helper for E2E Tests**:
>    For auth suites (`04`, `08`, `09`), provide an authenticated test helper or fixture that logs in with the existing seeded user (`test@example.com` or created tenant user) via `POST /api/tenant/auth/login`.

---

## Proposed Changes

### Component 1: Storefront Route & Backend SPA Handshake

#### [MODIFY] `app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderController.php`
- Replace broken `route('orders.show', ...)` call with SPA relative path `/orders/{$order->id}` or `/orders/track?order_number=...`.

```php
// In placeOrder() COD redirect handling:
if ($paymentMethod === PaymentMethod::COD) {
    if ($user && ! ($user instanceof User)) {
        return response()->json([
            'success' => true,
            'redirect_type' => 'internal',
            'redirect_url' => "/orders/track?order_number={$order->order_number}&phone=" . urlencode($user->phone ?? ''),
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    return response()->json([
        'success' => true,
        'redirect_type' => 'internal',
        'redirect_url' => "/orders/{$order->id}",
        'order_id' => $order->id,
        'order_number' => $order->order_number,
    ]);
}
```

#### [MODIFY] `resources/js/core/router/AppRouter.tsx`
- Import and mount `OrderShow` on `/orders/:id` instead of `PlaceholderPage`.

```tsx
function OrderDetailsRoute() {
  const { id } = useParams<{ id: string }>();
  return <OrderShow orderId={id} />;
}

// In Routes:
<Route path="/orders/:id" element={<OrderDetailsRoute />} />
```

---

### Component 2: Theme Test ID Instrumentation & Contract Decoupling

#### [MODIFY] `resources/js/themes/default/plugins/order-management/ThemedOrdersList.tsx`
- Add standard `data-testid` attributes:
  - `data-testid="orders-history-list"` on list container
  - `data-testid="order-history-item"` on each order card
  - `data-testid="order-number-display"` on order header
  - `data-testid="order-status-badge"` on order status badge
  - `data-testid="order-total-display"` on order total
  - `data-testid="view-order-details-button"` on View Details link
  - `data-testid="empty-orders-message"` on empty state container

#### [MODIFY] `resources/js/themes/default/plugins/order-management/components/OrderTypeSelection.tsx`
- Add `data-testid="order-type-delivery"` and `data-testid="order-type-takeaway"` on radio options.

#### [MODIFY] `resources/js/themes/default/plugins/locations-delivery/components/BranchSelection.tsx`
- Add `data-testid="branch-select-trigger"` on branch select dropdown.

#### [MODIFY] `tests/e2e/themes/theme.contract.ts`
- Define `IOrderTheme` interface with typed getters:
  - `getCheckoutForm(): Locator`
  - `getPlaceOrderButton(): Locator`
  - `getOrderSummary(): Locator`
  - `getOrderTypeDelivery(): Locator`
  - `getOrderTypeTakeaway(): Locator`
  - `getBranchSelect(): Locator`
  - `getOrdersList(): Locator`
  - `getOrderCards(): Locator`
  - `getOrderCard(index: number): Locator`
  - `getOrderNumber(card: Locator): Locator`
  - `getOrderStatus(card: Locator): Locator`
  - `getOrderTotal(card: Locator): Locator`
  - `getEmptyOrders(): Locator`
  - `getOrderDetails(): Locator`
- Add `readonly orders: IOrderTheme;` to `IThemeAdapter`.

#### [MODIFY] `tests/e2e/themes/base/BaseThemeAdapter.ts`
- Implement `BaseOrderTheme` class adhering to `IOrderTheme`.
- Instantiate `this.orders = new BaseOrderTheme(page)` inside `BaseThemeAdapter`.

---

### Component 3: Live E2E Test Suite (`tests/e2e/plugins/order-management/`)

Create 10 modular, high-speed live test specs:

#### [NEW] `tests/e2e/plugins/order-management/01-checkout-bootstrap.live.spec.ts`
- Verifies `/checkout` loads bootstrap payload (`/api/checkout-data`).
- Validates active branch selection, default payment method (COD), and guest fields presence.

#### [NEW] `tests/e2e/plugins/order-management/02-guest-cod-delivery.live.spec.ts`
- Adds Product 1 to cart, navigates to `/checkout`.
- Fills guest information (Name, Egyptian phone `01012345678`, selects Governorate/Area, street).
- Clicks `place-order-button`, intercepts `POST /api/orders/place`.
- Verifies 200 OK response with `order_id` and redirection to tracking/details.
- Verifies cart is cleared after order.

#### [NEW] `tests/e2e/plugins/order-management/03-guest-takeaway.live.spec.ts`
- Adds Product 1 to cart, navigates to `/checkout`.
- Switches order type to "Takeaway" (`web_takeaway`).
- Verifies delivery address section is omitted and delivery fee is 0 EGP.
- Places order and verifies `type: "web_takeaway"` in API response and confirmation.

#### [NEW] `tests/e2e/plugins/order-management/04-auth-customer-order.live.spec.ts`
- Logs in as seeded tenant user (`kofe`).
- Navigates to `/checkout` with cart items.
- Verifies registered delivery addresses dropdown appears (no guest form).
- Places order and verifies order is linked to customer user account.

#### [NEW] `tests/e2e/plugins/order-management/05-complex-cart-order.live.spec.ts`
- Adds Product 5 (`Simple Coffee`) with Variant ("سادة"), Weight (250g), and Addon ("Gensing").
- Navigates to `/checkout`, places order.
- Inspects backend response and order details: verifies variant, weight multiplier, and extra option are persisted.

#### [NEW] `tests/e2e/plugins/order-management/06-checkout-calculations.live.spec.ts`
- Validates live order summary mathematical formula:
  $$\text{Total} = \text{Subtotal} + \text{Delivery Fee} + \text{Tax} + \text{COD Fee} - \text{Discount}$$
- Tests area change updates delivery fee dynamically in the summary.

#### [NEW] `tests/e2e/plugins/order-management/07-validation-and-guards.live.spec.ts`
- Guards `/checkout` when cart is empty -> displays empty cart notice and disables order submission.
- Tests incomplete guest data (blank phone or name) triggers validation feedback.
- Tests unselected area triggers validation feedback.

#### [NEW] `tests/e2e/plugins/order-management/08-orders-history-list.live.spec.ts`
- Authenticates and navigates to `/orders`.
- Verifies order cards, order numbers, timestamps, and status badges.
- Tests pagination controls if page count > 1.
- Verifies empty order state for account with no orders.

#### [NEW] `tests/e2e/plugins/order-management/09-order-details-inspection.live.spec.ts`
- Navigates to `/orders/:id` for a placed order.
- Verifies order details container, items breakdown, item options, address summary, and total amount.
- Verifies 404 state when navigating to non-existent order ID `/orders/99999999`.

#### [NEW] `tests/e2e/plugins/order-management/10-order-status-lifecycle.live.spec.ts`
- Places an order and retrieves `order_number`.
- Sends `POST /api/order-status` with `{ orderNumber, status: 'processing' }`.
- Sends status transition to `delivered`.
- Verifies order details view updates status badge to reflect the new state.

---

## Verification Plan

### Automated Tests
Execute the entire modular test suite sequentially (`workers: 1`):
```powershell
# Run all OrderManagement E2E tests
npx playwright test tests/e2e/plugins/order-management --workers=1

# Run individual suite in headed mode with visual inspection
npx playwright test tests/e2e/plugins/order-management/02-guest-cod-delivery.live.spec.ts --headed

# Run complex cart integration suite
npx playwright test tests/e2e/plugins/order-management/05-complex-cart-order.live.spec.ts --headed
```

### Build & Lint Verification
```powershell
# Verify TypeScript and bundle compilation
npm run build

# Run PHP syntax check
php -l app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderController.php
```

### Manual Verification
1. Open browser at `http://kofe.localhost:8001`.
2. Add a product to cart and click "Proceed to Checkout".
3. Place a Guest COD order and verify smooth transition to order tracking or details without 500 errors.
4. Visit `/orders` and `/orders/:id` to inspect order history and detailed breakdown.
