# Kashier integration

Kashier processes card and wallet payments using its hosted JavaScript iFrame SDK and server-to-server webhooks.

**Plugin:** `payment-processing` · **Code:** [`KashierService`](../../app/Plugins/PaymentProcessing/src/Services/KashierService.php), [`KashierWebhookController`](../../app/Plugins/PaymentProcessing/src/Http/Controllers/KashierWebhookController.php), [`ThemedKashierPayment`](../../resources/js/themes/default/plugins/payment-processing/ThemedKashierPayment.tsx) · **Related:** [Online payments](../features/payments.md)

## Payment flow

1. At checkout, [`KashierService::createPaymentIntention`](../../app/Plugins/PaymentProcessing/src/Services/KashierService.php) generates a `merchantOrderId` (`ORD-{order_id}-{Ymd}-{random}`), computes an HMAC-SHA256 hash over `/?payment={merchantId}.{merchantOrderId}.{amount}.{currency}` using `kashier_api_key`, saves `merchant_order_id` on the order, and returns `/payments/{order_id}/kashier` as the checkout URL.
2. The SPA route `/payments/:id/kashier` fetches the signed Kashier SDK parameters from `GET /api/payments/{order}/kashier-config` (owner-authorized via [`AuthorizesOrderPayment`](../../app/Plugins/PaymentProcessing/src/Http/Requests/Concerns/AuthorizesOrderPayment.php)) and loads `https://payments.kashier.io/kashier-checkout.js` with `data-*` attributes.
3. Kashier posts payment events to `POST /api/webhooks/kashier` (`serverWebhook`) and redirects the browser to `GET /orders/{orderId}/payment/callback` (`merchantRedirect`).
4. The browser callback is read-only and maps the stored `payment_status` to `?status=success|failed|pending` while the SPA polls `GET /api/orders/{orderId}/payment-status`.

## Webhook verification

[`KashierWebhookController::handle`](../../app/Plugins/PaymentProcessing/src/Http/Controllers/KashierWebhookController.php) delegates to [`PaymentWebhookService::handleWebhook`](../../app/Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php) with the `kashier` gateway explicitly selected by route:

1. **Signature header:** requires `X-Kashier-Signature` (missing signature returns `400`).
2. **Fail closed:** if `kashier_api_key` is empty, throws `PaymentGatewayNotConfiguredException` (`503`).
3. **Mandatory signed keys:** requires `data.signatureKeys` to include `merchantOrderId`, `amount`, `currency`, `status`, and `transactionId`, sorts `signatureKeys`, builds an RFC 3986 query string, computes `HMAC-SHA256` with `kashier_api_key`, and compares with `hash_equals`.
4. **Order binding and idempotency:** checks `payment_events (gateway, transaction_id)` for duplicates, verifies amount in cents and currency match the order, and transitions `payment_status` (`SUCCESS`/`CAPTURED` -> `completed`, `PENDING` -> `processing`, `REFUNDED` -> `refunded`, else `failed`) via [`PaymentStateMachine`](../../app/Plugins/PaymentProcessing/src/Services/PaymentStateMachine.php).

`KashierService::refundTransaction` (`PUT {fepUrl}/v3/orders/{transactionId}/` with `Authorization: {kashier_secret_key}`) implements gateway refunds on the service interface, though no UI or route triggers it.

## Configuration

Configured per store in **إعدادات المتجر › المالية وبوابات الدفع** ([`PaymentSettingsSection`](../../app/Plugins/PaymentProcessing/src/Settings/PaymentSettingsSection.php)):

| Setting | Purpose | Default |
| --- | --- | --- |
| `kashier_merchant_id` | Kashier merchant ID (`MID-...`) | — |
| `kashier_api_key` | Payment hash and webhook HMAC key | — |
| `kashier_secret_key` | Refund API authorization secret | — |
| `kashier_mode` | `test` or `live` | `test` |
| `kashier_currency` | Currency code | `EGP` |
| `kashier_allowed_methods` | `card`, `wallet`, or `card,wallet` | `card` |

Set the server webhook URL in the Kashier dashboard to `https://<store-domain>/api/webhooks/kashier`.
