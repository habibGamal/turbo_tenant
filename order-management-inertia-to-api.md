# OrderManagement: Inertia-to-API Decoupling Plan

Migrating `OrderManagement` from tight Inertia coupling to decoupled REST API + Headless TanStack Query + Theme Overrides.

---

## Architecture Blueprint

- **Backend Controller**: Moved and refactored to `App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderController` (pure `JsonResponse`).
- **Tenant Routes**: Exposed `/api/orders`, `/api/orders/{orderId}`, `/api/checkout-data`, and `/api/orders/place`. Web routes render lightweight shells.
- **Frontend Domain Module**: `resources/js/plugins/order-management/`
  - `types/index.ts`: TypeScript contracts for orders, line items, checkout data, and responses.
  - `api/useOrders.ts`, `useOrderDetails.ts`, `useCheckoutData.ts`, `usePlaceOrder.ts`: Headless TanStack Query hooks.
  - `components/DefaultOrdersList.tsx`: Neutral fallback component for customer orders.
  - `index.ts`: Register default fallback on slot `customer.orders`.
- **Theme Presentation**:
  - `resources/js/themes/default/plugins/order-management/ThemedOrdersList.tsx`: Themed component for `default` theme.
  - `resources/js/themes/default/plugins/order-management/index.ts`: Register theme override for `customer.orders`.
  - Registered in `resources/js/plugins/registry.ts`.
  - Decoupled `MyOrders.tsx`, `OrderShow.tsx`, and `Checkout.tsx` to consume headless hooks and `<PluginSlot>`.

---

## Tasks

- [x] **Task 1: Scaffold Directory Structure**
  Ran `python .agent/skills/plugin-inertia-to-api/scripts/migrate_plugin.py OrderManagement` and established decoupled folders.

- [x] **Task 2: Refactor & Relocate Backend API Controller**
  Relocated `OrderController.php` to `app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderController.php`, removing all Inertia dependencies and returning pure `JsonResponse` for all actions (`index`, `show`, `checkoutData`, `placeOrder`).

- [x] **Task 3: Update Plugin Tenant Routes**
  In `app/Plugins/OrderManagement/routes/tenant.php`, mapped REST API endpoints under `/api` and web entrypoint shells under web middleware.

- [x] **Task 4: Implement Frontend Domain Types & TanStack Query Hooks**
  Defined TypeScript contracts in `resources/js/plugins/order-management/types/index.ts` and headless hooks in `resources/js/plugins/order-management/api/` (`useOrders`, `useOrderDetails`, `useCheckoutData`, `usePlaceOrder`).

- [x] **Task 5: Implement Default Fallback & Slot Registration**
  Built `DefaultOrdersList.tsx` and registered it on slot `customer.orders` in `resources/js/plugins/order-management/index.ts`.

- [x] **Task 6: Implement Theme Override for Default Theme**
  Built `ThemedOrdersList.tsx` and registered theme override in `resources/js/themes/default/plugins/order-management/` and hooked into `resources/js/plugins/registry.ts`.

- [x] **Task 7: Decouple Theme Pages (MyOrders, OrderShow, Checkout)**
  Updated `MyOrders.tsx` to mount `<PluginSlot name="customer.orders" />`, and decoupled `OrderShow.tsx` & `Checkout.tsx` to consume the headless API hooks.

- [x] **Task 8: Verification & Quality Audit**
  Verified with `npx tsc --noEmit` (0 errors), `php artisan route:list` for order & checkout endpoints, and checked PHP syntax.
