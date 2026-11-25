# Payment webhook rate limiting and monitoring

## What
Apply rate limiters to `POST /api/webhooks/paymob`, `POST /api/webhooks/kashier`, and `GET /orders/{orderId}/payment/callback`, and add alerting for repeated invalid webhook signatures and stale `pending` online payments.

## Why
Currently `app/Plugins/PaymentProcessing/routes/tenant.php` has no `throttle` middleware on webhook or callback routes, and there is no automated alert when signature failures spike or when a customer completes a callback redirect without a matching webhook arriving within 24 hours.

## Where in code
- `app/Plugins/PaymentProcessing/routes/tenant.php`
- `app/Providers/AppServiceProvider.php`
- `app/Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php`
- `routes/console.php`

## Done when
- Named rate limiters throttle webhook and callback endpoints per IP.
- Repeated signature failures notify store admins, and a scheduled check flags online orders stuck in `pending` 24 hours after callback.
- Feature tests verify rate limiting and stale-payment detection.
