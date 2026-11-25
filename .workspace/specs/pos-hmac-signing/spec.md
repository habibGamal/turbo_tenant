# Per-branch POS HMAC signing and route ownership

## What
Upgrade POS ↔ website authentication from the single per-tenant bearer `integration_token` and static `X-Api-Key` to per-branch HMAC-SHA256 request signing (`X-Turbo-Timestamp`, `X-Turbo-Signature`, `X-Turbo-Branch`, 300s replay window) in both directions, add secret rotation with a 24-hour overlap window, and move `POST /api/order-status` from `order-management` into `pos-integration`.

## Why
Currently `POST /api/order-status` lives in `OrderManagement` behind a single tenant-wide `integration_token`, and outbound `POSApiClient` requests carry `X-Api-Key` without request payload signatures or replay protection.

## Where in code
- `app/Plugins/OrderManagement/routes/tenant.php`
- `app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderStatusController.php`
- `app/Plugins/OrderManagement/src/Http/Middleware/VerifyIntegrationToken.php`
- `app/Plugins/PosIntegration/routes/tenant.php`
- `app/Plugins/PosIntegration/src/Services/POS/POSApiClient.php`
- `app/Plugins/LocationsDelivery/src/Models/Branch.php`

## Done when
- Coordinated with the POS repo (`ai_rewrite_turbo_restaurant`), both outbound POS requests and inbound `/api/order-status` callbacks verify per-branch HMAC signatures with timestamp/replay checks.
- `POST /api/order-status` is owned by `pos-integration`.
- Secret rotation keeps the previous secret valid during the grace window, and security feature tests pass.
