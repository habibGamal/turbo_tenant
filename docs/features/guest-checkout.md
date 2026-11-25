# Guest checkout and order tracking

## Overview

Lets customers order without an account by giving name and phone (plus address for delivery), and later track the order with its number and phone, or with a signed tracking link.

**Status:** live · **Plugin:** `guest-checkout` ([plugin.json](../../app/Plugins/GuestCheckout/plugin.json)) · **Depends on:** order-management, locations-delivery · **Related:** [Checkout](checkout-and-orders.md), [Payments](payments.md)

## How to use

### Customer: order as a guest

1. Go to checkout without signing in. The **Guest Information** section asks for name and phone ("Please provide your name and phone number"), optional email, and for delivery the area and address fields.
2. Place the order as usual. Your details are remembered in this browser (`localStorage` key `turbo_tenant_guest_data`) for next time.
3. Keep the order number shown on confirmation.

### Customer: track an order

1. Open **Track Order** (`/track-order` or `/orders/track`).
2. Enter **Order Number** (e.g. `ORD-12345678`) and your phone. You see the order status, items and totals.
3. "Order not found" means the number and phone do not match an order placed as a guest.

### Store admin

**العملاء والتقييمات › زوار المتجر** (Customers & reviews › Store visitors, [`GuestUserResource`](../../app/Plugins/GuestCheckout/src/Filament/Resources/GuestUsers/GuestUserResource.php)) lists guest identities and their orders (read-only list and view pages).

### Rules customers notice

- Without this plugin, checkout requires an account.
- Phone country code defaults to +20.

## How it works

1. **Checkout permission:** [`GuestCheckoutPermissionPipe`](../../app/Plugins/GuestCheckout/src/Pipelines/Pipes/GuestCheckoutPermissionPipe.php) (priority 325) authorizes guest orders so [`ValidateUserAuthenticatedPipe`](../../app/Plugins/OrderManagement/src/Pipelines/Pipes/Validation/ValidateUserAuthenticatedPipe.php) lets them through. The SPA's [`CheckoutGuard`](../../resources/js/core/router/guards/CheckoutGuard.tsx) allows `/checkout` when the plugin is active.
2. **Validation:** [`GuestDataRulesContributor`](../../app/Plugins/GuestCheckout/src/Http/Rules/GuestDataRulesContributor.php) adds `guest_data.name`, `guest_data.phone` (required), `phone_country_code`, `email`, address fields, and `guest_data.area_id` (required for delivery, active area).
3. **Customer:** [`GuestDataCustomerResolver`](../../app/Plugins/GuestCheckout/src/Resolvers/GuestDataCustomerResolver.php) (tag `order.customer.resolvers`) finds or creates a [`GuestUser`](../../app/Plugins/GuestCheckout/src/Models/GuestUser.php) by phone + country code ([`GuestUserService`](../../app/Plugins/GuestCheckout/src/Services/GuestUserService.php)); the order gets `guest_user_id`. The guest's cart is the session cart.
4. **After placement:** [`RememberGuestOrderSession`](../../app/Plugins/GuestCheckout/src/Listeners/RememberGuestOrderSession.php) (on `OrderCreated`) stores the order id in the session `guest_order_ids`, which lets the same browser read payment status. [`GuestTrackingResponseContributor`](../../app/Plugins/GuestCheckout/src/Http/Response/GuestTrackingResponseContributor.php) adds a tracking token to the place-order response, and [`GuestPaymentStatusResponseContributor`](../../app/Plugins/GuestCheckout/src/Http/Response/GuestPaymentStatusResponseContributor.php) adds it to `GET /api/orders/{orderId}/payment-status` (`order.payment-status.response`) so online payment callbacks link guests straight to signed order tracking.
5. **Tracking:** [`GuestCheckoutApiController::track`](../../app/Plugins/GuestCheckout/src/Http/Controllers/Api/GuestCheckoutApiController.php) accepts `order_number` + `token` (HMAC-SHA256 with `APP_KEY`, [`GuestTrackingTokenService`](../../app/Plugins/GuestCheckout/src/Services/GuestTrackingTokenService.php)), or `order_number` + `phone` (+ `phone_country_code`). Both return 404 "Order not found" on any mismatch.

### Data

- `guest_users`: `name`, `email`, `phone`, `phone_country_code` (default `+20`), optional delivery fields (`street`, `building`, `floor`, `apartment`, `city`, `area_id`), indexed on `(phone, phone_country_code)` and `email`. [`GuestUserService::convertToUser`](../../app/Plugins/GuestCheckout/src/Services/GuestUserService.php) transfers a guest's orders to a registered `User` if called.
- `orders`: `user_id` is nullable and `guest_user_id` references `guest_users` (`nullOnDelete`), with `customer_name`, `customer_phone`, and `customer_email` snapshotted on the order.

## Configuration

No settings. Activate after order-management and locations-delivery. Rotating `APP_KEY` invalidates issued tracking tokens.

## Testing

- Automated: [GuestOrderTest](../../tests/Feature/GuestOrderTest.php), [GuestOrderTrackingTest](../../tests/Feature/GuestOrderTrackingTest.php), [GuestUserServiceTest](../../tests/Unit/GuestUserServiceTest.php), [GuestUserPhoneTest](../../tests/Unit/Services/GuestUserPhoneTest.php).
- Browser: [guest-checkout.live.spec.ts](../../tests/e2e/plugins/guest-checkout.live.spec.ts), [02-guest-cod-delivery](../../tests/e2e/plugins/order-management/02-guest-cod-delivery.live.spec.ts), [03-guest-takeaway](../../tests/e2e/plugins/order-management/03-guest-takeaway.live.spec.ts), [11-guest-tracking-security](../../tests/e2e/plugins/order-management/11-guest-tracking-security.live.spec.ts).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Guest sent to login at checkout | Plugin inactive | Activate `guest-checkout` |
| "Order not found" when tracking | Phone typed differently (country code, leading zero) or order placed while signed in | Use the phone exactly as entered; signed-in orders are under My Orders |
| Guest cannot see payment result in another browser | Payment status needs the session that placed the order | Track by number + phone |
| Tracking link stopped working | `APP_KEY` changed | Track by number + phone |

## History

- 2026-02: guest ordering, tracking, guest emails and admin resource.
