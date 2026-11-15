# Phase 1 (P0): Payment Callbacks & Webhooks

> **Plugin:** `payment-processing`
> **Covers:** I-1 browser callback, I-2 Paymob webhook, I-3 Kashier webhook, O-2 gateway secrets

---

## 1. Findings

### 1.1 CRITICAL: the browser callback can mark any order paid or cancelled
- **Route:** `GET /orders/{orderId}/payment/callback` → `PaymentController::paymentCallback` → `PaymentCallbackService::handleCallback`
- `PaymentCallbackService.php:40` and `:123` only check the signature **when one is present**: `if ($signature && ! valid)` / `if ($hmac && ! valid)`. Leaving it out skips the check.
- `KashierService::validateCallbackHmac` (`KashierService.php:137`) returns `true` whenever `merchantOrderId` or `orderReference` is in the data, and `merchantOrderId` is exactly what sends a request down the Kashier branch. So the Kashier callback is **never** verified.
- The order is looked up by the integer `orderId` in the URL (`:20`). Nothing checks that the payload's `merchantOrderId`, amount or currency match that order.
- On success it sets `payment_status=completed` and `pos_status=READY`, so the order goes to the kitchen. On failure it sets `status=cancelled` (`:93`, `:178`).
- It is a **GET**, so a link or `<img>` tag is enough to fire it.

**Exploit:** `GET /orders/1234/payment/callback?merchantOrderId=x&paymentStatus=SUCCESS` gets order 1234 paid without paying. Walking ids with `?success=false` cancels every order.

### 1.2 HIGH: an empty secret means anyone can forge a signature
- `PaymobService` uses `hash_hmac('sha512', ..., $this->hmacSecret)`, and the secret defaults to `''` (`PaymentProcessingServiceProvider`, `PAYMOB_HMAC_SECRET` default `''`). Kashier's `apiKey` also defaults to `''`.
- A tenant that enabled payment-processing but never pasted its secret accepts webhooks signed with an empty key, which anyone can compute.

### 1.3 HIGH: the Kashier webhook signs only the keys the caller chooses
- `KashierService::validateWebhookPayload` (`:291`) builds the signed string from `data.signatureKeys`, a list **taken from the request**. Kashier's own deliveries are fine, but the code never requires `merchantOrderId`, `status`, `amount` or `transactionId` to be among the signed keys.
- `:315` logs `expected_signature` together with the query string.

### 1.4 HIGH: webhooks are checked with whichever gateway is active, not the one in the URL
- Both `PaymobWebhookController` and `KashierWebhookController` inject the same `PaymentWebhookServiceInterface`, which takes `PaymentGatewayInterface`, bound to `getActiveGateway()`.
- If a tenant switches from Paymob to Kashier, every in-flight Paymob webhook is checked with Kashier's algorithm and rejected. Payments that really succeeded stay `pending`.

### 1.5 MEDIUM: no amount check, no state machine, no replay protection
- `PaymentWebhookService::handleWebhook` (`:37`) writes the incoming status onto the order without:
  - comparing `amount_cents` and currency with the order total,
  - checking the current status (a replayed `failed` downgrades a `completed` order; a replayed `completed` revives a cancelled one),
  - de-duplicating by `transaction_id`.
- `PaymobWebhookController` also accepts `hmac` from the JSON body (`$webhookData['hmac']`). Paymob sends it as a query parameter; accept only that.

### 1.6 MEDIUM: secrets and full payloads are logged
- `PaymentController.php:33` logs the full callback query string (`hmac`, card mask, amount).
- `PaymobWebhookController` logs the full webhook body on receipt and again on failure.
- `KashierWebhookController` logs the full body when the signature is missing or processing fails.

### 1.7 MEDIUM: gateway secrets can leak between tenants in long-lived processes
- `PaymobService`, `KashierService`, `PaymentGatewayFactory` and `PaymentProcessingConfig` are **singletons** that read tenant settings once. In a queue worker or Octane, tenant B reuses tenant A's keys. Today webhooks run under PHP-FPM so it does not trigger there, but any queued payment job would.

---

## 2. Target design

```
Browser redirect ──► GET /orders/{id}/payment/callback
                     │  (read-only: NEVER changes payment_status)
                     │  1. verify signature if gateway sends one → else ignore params
                     │  2. if order still pending → ask gateway API for transaction status (server-to-server)
                     │  3. redirect SPA to /orders/{id}/payment/callback?status=<from DB>
                     ▼
Gateway server ────► POST /api/webhooks/{gateway}
                     │  1. resolve gateway FROM THE ROUTE, not settings
                     │  2. reject if tenant secret empty (fail closed)
                     │  3. verify HMAC (Kashier: require mandatory signed keys)
                     │  4. load order by merchant_order_id; assert amount+currency match
                     │  5. idempotency: skip if transaction_id already processed
                     │  6. apply allowed transition only (PaymentStateMachine)
                     │  7. audit row in payment_events
```

---

## 3. Tasks

### P0: ship together
- [ ] **T1.1** Make the callback read-only. `PaymentCallbackService::handleCallback` no longer writes `payment_status`, `status` or `pos_status`. It (a) verifies the signature with the gateway named in the order's `payment_method`, (b) if the order is still pending, calls the gateway's transaction-inquiry API (Paymob `GET /api/acceptance/transactions/{id}`, Kashier order-status API) and passes the verified result to the same code the webhook uses, and (c) redirects with the status **read from the database**.
- [ ] **T1.2** Remove the "signature optional" pattern. Replace `if ($sig && !valid)` with `if (! $sig || ! valid) → reject`. Delete the `return true` short-circuit in `KashierService::validateCallbackHmac`.
- [ ] **T1.3** Fail closed on missing secrets. In `PaymobService` and `KashierService` constructors, or in a `ensureConfigured()` guard, throw `PaymentGatewayNotConfigured` when the HMAC or API secret is `''`. Webhook controllers map it to `503` with no state change.
- [ ] **T1.4** Choose the gateway by route. `PaymobWebhookController` uses `PaymentGatewayFactory::getGateway('paymob')` and `KashierWebhookController` uses `getGateway('kashier')`. Change `PaymentWebhookService::handleWebhook` to take the gateway as an argument.
- [ ] **T1.5** Bind the message to the order. After loading by `merchant_order_id`, reject unless the amount (in cents) equals `round($order->total * 100)`, the currency equals the configured currency, and `order.payment_method` matches the gateway.
- [ ] **T1.6** Feature tests (`tests/Feature/Security/PaymentCallbackSecurityTest.php`):
  - unsigned Paymob callback with `success=true` → order stays `pending`
  - Kashier callback with `merchantOrderId` and no signature → order stays `pending`
  - `success=false` callback → order **not** cancelled
  - webhook with empty tenant secret → 503, no change
  - Kashier webhook to a Paymob-active tenant → verified with the Kashier algorithm

### P1
- [ ] **T1.7** Kashier: require these keys in `signatureKeys`: `merchantOrderId`, `orderId`, `amount`, `currency`, `status`, `transactionId` (confirm the exact list against Kashier's docs). Reject if any is missing. Stop logging `expected_signature`.
- [ ] **T1.8** Paymob: read `hmac` only from the query string.
- [ ] **T1.9** Add `PaymentStateMachine` with allowed transitions: `pending → processing|completed|failed`, `processing → completed|failed`, `completed → refunded`. Everything else is a logged no-op.
- [ ] **T1.10** Idempotency. Add a `payment_events` table (`gateway`, `transaction_id` unique, `merchant_order_id`, `type`, `amount_cents`, `verified`, `received_at`, `result`). Skip duplicates and return 200 so the gateway stops retrying.
- [ ] **T1.11** Make the gateway services **non-singletons**, or reset them on `TenancyInitialized` (see `04_cross_cutting_controls.md` §3).
- [ ] **T1.12** Log hygiene: keep only `gateway, merchant_order_id, transaction_id, status, result` (see `04` §1).

### P2
- [ ] **T1.13** Optional: per-gateway IP allowlist (Paymob and Kashier publish their egress ranges) as an extra layer. It does **not** replace HMAC.
- [ ] **T1.14** Alert, by Filament notification or email to the tenant admin, after N signature failures within 10 minutes.

---

## 4. Files touched

- `app/Plugins/PaymentProcessing/src/Services/PaymentCallbackService.php`
- `app/Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php`
- `app/Plugins/PaymentProcessing/src/Services/PaymobService.php`
- `app/Plugins/PaymentProcessing/src/Services/KashierService.php`
- `app/Plugins/PaymentProcessing/src/Http/Controllers/{Paymob,Kashier}WebhookController.php`
- `app/Plugins/PaymentProcessing/src/Http/Controllers/PaymentController.php`
- `app/Plugins/PaymentProcessing/PaymentProcessingServiceProvider.php`
- new `app/Plugins/PaymentProcessing/src/Services/PaymentStateMachine.php`
- new migration `app/Plugins/PaymentProcessing/database/migrations/tenant/*_create_payment_events_table.php`

## 5. Rollout notes

- T1.1 changes UX: a customer may briefly see "processing" until the webhook arrives. The SPA callback page should poll `GET /api/orders/{id}/payment-status` (that endpoint already checks ownership) for about 30 seconds.
- Before deploying T1.3, list the tenants with `payment-processing` active and an empty secret, and contact them. Their online payments will stop until they configure the secret, which is the intended result.
