# Defer guest profile persistence until checkout validation passes

## What
Stop `GuestUserService::findOrCreate()` from creating or overwriting `guest_users` rows before `PlaceOrderService` validation succeeds, and rely on order snapshots instead of mutating shared guest profiles on repeat orders.

## Why
`CheckoutCustomerResolver` currently calls `GuestUserService::findOrCreate()` during request resolution before the order validation pipeline runs, so failed orders still persist guest rows and unauthenticated requests with an existing phone overwrite that guest's stored name and address.

## Where in code
- `app/Plugins/GuestCheckout/src/Resolvers/GuestDataCustomerResolver.php`
- `app/Plugins/GuestCheckout/src/Services/GuestUserService.php`
- `app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderController.php`

## Done when
- Invalid checkouts do not create or mutate `guest_users` rows.
- Repeat orders with the same phone do not overwrite earlier guest profiles or past order details.
- Feature tests cover validation-failure rollback and non-mutation.
