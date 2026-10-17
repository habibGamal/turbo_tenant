# Phase 2 Migration Plan: Consolidated Commerce Engine & Flow (Zero-Coupling Architecture)

> **Milestone:** M2 (Consolidated: Cart, Order, Guest Mode, Payments & Promotions)  
> **Architecture Standard:** Zero Compile-Time Coupling (Microkernel Pattern)  
> **Plugins:** `cart-engine`, `order-management`, `guest-checkout`, `payment-processing`, `coupons-promotions`  
> **Scope:** Basket calculation, order state machine, checkout, payment processing, guest checkout, promo discounts, and extensible pipeline buses  
> **Target Path:** `.plan/migration_to_plugins/02_phase2_cart_and_order_engine.md`  

---

## 1. Objectives & Overview

This consolidated phase unites the foundational commercial transaction layer and all customer checkout extensions into a clean, zero-bridge, zero-coupling architecture:
1. **`cart-engine`**: Stateless and session/customer-bound carts, line item modifier math, weight multipliers, and cart synchronization.
2. **`order-management`**: Order persistence, status progression, item records, and host **Pipeline Bus** (`app/Plugins/OrderManagement/src/Pipelines/`). Has zero imports of `Coupon` or `GuestUser`.
3. **`guest-checkout`**: Guest accounts, friction-free ordering, route redirect guard to `/login` when deactivated, and order tracking. Plugs directly into `OrderValidationPipeline` via `AttributeBag`.
4. **`payment-processing`**: Gateways (Paymob, Kashier, COD), webhooks, callbacks, and COD fee pricing adjustment pipe.
5. **`coupons-promotions`**: Promo codes, usage conditions, validation pipe, and discount pricing adjustment pipe.

---

## 2. Zero-Coupling Pipeline Architecture

### 2.1 The Two Fundamental Decisions:
1. **Does making Contexts extensible violate the Pipeline pattern?**  
   **No.** In an open plugin ecosystem, a Context must be **Open for Extension, Closed for Modification (OCP)** (like PSR-7 `ServerRequestInterface` attributes or Symfony Request attribute bag). Fixed core domain invariants (`user`, `cartItems`, base `subTotal`) are fixed properties, while plugin data lives in an extensible **Attribute Bag** and **PricingAdjustment Ledger**.
2. **Zero Compile-Time Coupling:**  
   `OrderManagement` never imports `Coupon` or `GuestUser`. Extension pipes read/write from `$context->set(...)` and `$context->addAdjustment(...)`. Eloquent relations (`Order::guestUser()` and `Order::coupon()`) are registered at runtime via `Model::resolveRelationUsing()`.

---

## 3. Pipeline Contracts & Contexts in Code

- **`OrderValidationContext`**: Holds `?Authenticatable $user`, `array $cart`, `array $payload`, and dynamic `AttributeBag` (`get()`, `set()`, `authorize()`). Zero plugin model imports.
- **`PricingAdjustment` Ledger Model**: DTO for signed adjustments (`+$amount` or `-$amount`) with types (`discount`, `tax`, `fee`, `shipping`, `surcharge`).
- **`PricingCalculationContext`**: Holds `$subTotal`, `$cartItems`, `$payload`, `$adjustments` ledger, and computes `$totals`. Zero plugin model imports.
- **`OrderPipelineRegistry`**: Singleton registering validation pipes and pricing pipes.

---

## 4. Plugin Manifests & Dependencies

| Plugin ID | Studly Name | Category | Primary Dependencies | Code Highlights |
| :--- | :--- | :--- | :--- | :--- |
| **`cart-engine`** | `CartEngine` | `foundation` | `catalog-products` | `Cart`, `CartItem`, `CartItemExtra`, `CartService`, `CartController`, React cart components. |
| **`order-management`** | `OrderManagement` | `foundation` | `cart-engine`, `locations-delivery` | `Order`, `OrderItem`, `PlaceOrderService`, `OrderPipelineRegistry`, `OrderValidationPipeline`, `PricingCalculationPipeline`, Orders Filament resource. |
| **`guest-checkout`** | `GuestCheckout` | `commerce` | `order-management` | `GuestUser`, `GuestUserService`, `GuestCheckoutPermissionPipe`, `EnsureCheckoutAccessMiddleware`, GuestUsers Filament resource, `/orders/track`. |
| **`payment-processing`**| `PaymentProcessing` | `commerce` | `order-management` | `PaymentGatewayFactory`, `PaymobService`, `KashierService`, `ApplyCodFeePipe`, Webhooks, Callbacks, `Kashier.tsx`. |
| **`coupons-promotions`** | `CouponsPromotions` | `marketing` | `cart-engine`, `order-management` | `Coupon`, `CouponService`, `ValidateCouponPipe`, `ApplyCouponDiscountPipe`, Coupons Filament resource, `/coupons/validate`. |

---

## 5. Verification Checklist
1. **Zero-Coupling Test:** Assert `OrderValidationPipeline` and `PricingCalculationPipeline` run and calculate with zero other plugins installed.
2. **Dynamic Relation Test:** Assert `$order->guestUser` and `$order->coupon` resolve through `Model::resolveRelationUsing()` when plugins are active.
3. **Plugin Discovery:** `php artisan plugin:discover` registers all 5 plugins.
4. **Guest Mode Gate:** Deactivation redirects to `/login` with flash warning.
5. **Full Suite:** Run unit and feature tests with `php -d memory_limit=512M artisan test`.
