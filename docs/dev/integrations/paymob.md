# Paymob integration

Paymob (Accept) processes card and mobile wallet payments for stores using its Unified Intention API and hosted Unified Checkout.

**Plugin:** `payment-processing` · **Code:** [`PaymobService`](../../../app/Plugins/PaymentProcessing/src/Services/PaymobService.php), [`PaymobWebhookController`](../../../app/Plugins/PaymentProcessing/src/Http/Controllers/PaymobWebhookController.php) · **Related:** [Online payments](../features/payments.md)

## Payment flow

1. At checkout, [`PaymobService::createPaymentIntention`](../../../app/Plugins/PaymentProcessing/src/Services/PaymobService.php) sends `POST {paymob_base_url}/v1/intention/` with `Authorization: Token <paymob_secret_key>`.
2. Amount is sent in piasters (`round(order.total * 100)`), `items` is empty, and `billing_data` defaults missing optional fields (`NA`, `Cairo`, `EG`) while sending `merchant_order_id` (`ORD_<order_number>_<timestamp>`).
3. Paymob returns an intention `id` and `client_secret`; the order stores `merchant_order_id` and `paymob_order_id`.
4. The customer is redirected to `{paymob_base_url}/unifiedcheckout/?publicKey={paymob_public_key}&clientSecret={client_secret}`.
5. Paymob posts the server-to-server transaction callback to `POST /api/webhooks/paymob` (`notification_url`) and redirects the browser to `GET /orders/{orderId}/payment/callback` (`redirection_url`).
6. The browser callback is read-only and maps the stored `payment_status` to `?status=success|failed|pending` while the SPA polls `GET /api/orders/{orderId}/payment-status`.

## Webhook verification

[`PaymobWebhookController::handle`](../../../app/Plugins/PaymentProcessing/src/Http/Controllers/PaymobWebhookController.php) delegates to [`PaymentWebhookService::handleWebhook`](../../../app/Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php) with the `paymob` gateway explicitly selected by route:

1. **HMAC source:** read only from the `hmac` query parameter (missing HMAC returns `400`).
2. **Fail closed:** if `paymob_hmac_secret` is empty, throws `PaymentGatewayNotConfiguredException` (`503`).
3. **Signature:** concatenates 20 fields from the transaction object (`amount_cents`, `created_at`, `currency`, `error_occured`, `has_parent_transaction`, `id`, `integration_id`, `is_3d_secure`, `is_auth`, `is_capture`, `is_refunded`, `is_standalone_payment`, `is_voided`, `order.id`, `owner`, `pending`, `source_data.pan`, `source_data.sub_type`, `source_data.type`, `success`) with boolean values formatted as `'true'`/`'false'`, computes `HMAC-SHA512` with `paymob_hmac_secret`, and compares with `hash_equals`.
4. **Order binding and idempotency:** checks `payment_events (gateway, transaction_id)` for duplicates, verifies `amount_cents == round(order.total * 100)` and currency match, and transitions `payment_status` through [`PaymentStateMachine`](../../../app/Plugins/PaymentProcessing/src/Services/PaymentStateMachine.php).

`PaymobService::refundTransaction` (`POST /api/acceptance/void_refund/refund`) implements gateway refunds on the service interface, though no UI or route triggers it.

## Configuration

Configured per store in **إعدادات المتجر › المالية وبوابات الدفع** ([`PaymentSettingsSection`](../../../app/Plugins/PaymentProcessing/src/Settings/PaymentSettingsSection.php)):

| Setting | Purpose | Default |
| --- | --- | --- |
| `paymob_base_url` | Paymob API base URL | `https://accept.paymob.com` |
| `paymob_public_key` | Unified Checkout public key | — |
| `paymob_secret_key` | Intention and refund API token | — |
| `paymob_integration_ids` | Comma-separated Paymob integration IDs | — |
| `paymob_hmac_secret` | HMAC-SHA512 webhook verification secret | — |
| `paymob_currency` | Currency code | `EGP` |
| `paymob_mode` | `test` or `live` | `test` |

`PAYMOB_*` variables in `.env` and [config/paymob.php](../../../config/paymob.php) are not read at runtime; credentials come from tenant `settings`.

Set the Transaction Processed Callback URL in the Paymob dashboard to `https://<store-domain>/api/webhooks/paymob`.
