# Master Plan: Security of Third-Party-Facing APIs

> **System:** Turbo Tenant SaaS (Laravel 12 / Stancl Tenancy 3.9 / plugin microkernel)
> **Path:** `.plan/security/`
> **Written:** 2026-10-28, from a code audit of branch `refactoring` (HEAD `facdac3`)
> **Scope:** every endpoint that another *system* (not a browser customer) calls into, plus every outbound call this app makes to a third party.

---

## 1. Why this plan exists

The storefront (customer-facing) API had a security pass on 2026-10-25 (CSRF, ownership checks, `plugin.active` gating). The endpoints that **payment gateways, the POS, and Google** talk to were not covered. The audit found that several of them **trust whatever the caller sends**. The worst cases need nothing more than a browser:

- `GET /orders/{id}/payment/callback?success=true` marks any order as **paid**, and the POS then receives it as ready to cook.
- `GET /orders/{id}/payment/callback?success=false` **cancels** any order. Order ids are sequential integers.
- `POST /api/order-status` lets anyone with an order number set any order's status.

---

## 2. Inventory of third-party surfaces

### Inbound (a third party calls us)

| # | Endpoint | Caller | Auth today | Risk |
|---|---|---|---|---|
| I-1 | `GET /orders/{orderId}/payment/callback` | Customer browser redirected by Paymob / Kashier | **Signature optional**; Kashier check always returns `true` | **Critical** |
| I-2 | `POST /api/webhooks/paymob` | Paymob servers | HMAC-SHA512, but the key can be empty; hmac also accepted from the body | High |
| I-3 | `POST /api/webhooks/kashier` | Kashier servers | HMAC-SHA256 over keys the **caller chooses** (`signatureKeys`); validated with the *active* gateway, not Kashier | High |
| I-4 | `POST /api/order-status` | Larament POS | **None** (no auth, no CSRF, `authorize()` returns `true`) | **Critical** |
| I-5 | `GET /auth/google/native-callback?access_token=` | Mobile app with a Google token | Accepts **any** Google access token; the audience is not checked | High |
| I-6 | `GET /auth/google/callback` | Google OAuth redirect | Socialite `state` check (OK) | Low |

### Outbound (we call a third party)

| # | Target | Code | Risk |
|---|---|---|---|
| O-1 | Larament POS (`branch.link` + `/api/...`) | `PosIntegration/src/Services/POS/POSApiClient.php` | No auth, URL set by the tenant admin (SSRF), full PII payload logged. TLS verification is off (`verify => false`) **on purpose**: branch POS servers use self-signed certs, and the owner accepted this on 2026-10-28. Out of scope. |
| O-2 | Paymob / Kashier APIs | `PaymobService`, `KashierService` | Secrets kept in plain text in the tenant `settings` table; singletons hold one tenant's keys for the whole process |
| O-3 | Expo push | `PushNotifications` | Low; included for the logging review only |

---

## 3. Phase index

| File | Covers | Priority |
|---|---|---|
| [`01_payment_callbacks_and_webhooks.md`](01_payment_callbacks_and_webhooks.md) | I-1, I-2, I-3, O-2 | **P0**: ship first |
| [`02_pos_integration_inbound_and_outbound.md`](02_pos_integration_inbound_and_outbound.md) | I-4, O-1 | **P0** (I-4), P1 (O-1) |
| [`03_google_native_login.md`](03_google_native_login.md) | I-5, I-6 | P1 |
| [`04_cross_cutting_controls.md`](04_cross_cutting_controls.md) | Logging, rate limits, secrets at rest, replay protection, tests | P1 / P2 |

---

## 4. Principles every fix must follow

1. **Fail closed.** A missing signature, a missing secret, or an unknown gateway rejects the request. "If a signature exists, check it" is never allowed.
2. **The browser redirect is never proof of payment.** Only a verified server-to-server webhook, or an authenticated API lookup to the gateway, can mark an order paid.
3. **Bind every message to the order it claims to be about.** Check `merchant_order_id`, the amount, the currency and the gateway against the stored order before changing it.
4. **State only moves forward.** Payment and order status follow an allowed-transition table, so a replayed or late message cannot downgrade `completed` or revive `cancelled`.
5. **One secret per tenant per integration**, kept encrypted, never logged, never compared with `==`.
6. **Every inbound endpoint is rate limited** and writes an audit row (who, what, result), without secrets or card data.

---

## 5. Definition of done (whole plan)

- [ ] All P0 items merged with feature tests that **prove the attack fails** (forged callback, unsigned order-status, empty-secret webhook, replayed webhook).
- [ ] `grep -rn "if (\$signature &&\|if (\$hmac &&" app/` returns nothing.
- [ ] No log line contains a password, access token, HMAC, card PAN fragment or full webhook body.
- [ ] Each tenant has a settings UI for its POS shared secret and its gateway secrets, stored encrypted.
- [ ] A runbook for rotating each secret exists in `docs/`.
