# Phase 4: Cross-Cutting Controls for Integration Endpoints

> **Covers:** logging, secrets at rest, tenant isolation of integration services, rate limits, monitoring, tests

---

## 1. Log hygiene (P1)

The audit found secrets and PII written to `storage/logs` in these places:

| Location | What leaks |
|---|---|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php:33` | `logger()->info('LoginRequest', $request->all())`: **plaintext passwords** (P0, a one-line fix) |
| `PaymentController.php:33` | full payment callback query: `hmac`, masked card, amounts |
| `PaymobWebhookController` (receipt and failure) | full webhook body: card mask, billing data, customer email/phone |
| `KashierWebhookController` (missing signature, failure) | full webhook body |
| `KashierService.php:315` | `expected_signature` and the signed query string |
| `POSApiClient::sendOrder` | full order payload (name, phone, address) and full POS response |

**Tasks**
- [ ] **T4.1** Delete the login request log line (P0).
- [ ] **T4.2** Add `App\Support\Logging\Redactor::forIntegration(array $payload, array $allow)`, which keeps only allow-listed keys. Use it in all the places above.
- [ ] **T4.3** Add a Monolog processor that masks keys matching `/(password|token|secret|hmac|signature|api_key|pan|card)/i` anywhere in the context, as a safety net.
- [ ] **T4.4** Rotate or purge existing log files that contain passwords, once T4.1 is deployed.

## 2. Secrets at rest (P1)

- Gateway secrets (`PAYMOB_SECRET_KEY`, `PAYMOB_HMAC_SECRET`, `KASHIER_API_KEY`, `KASHIER_SECRET_KEY`) and the new POS secrets are stored as plain values in the tenant `settings` table.
- [ ] **T4.5** Add an `encrypted` flag to `SettingKey`, so `SettingService::set()` and `get()` call `Crypt::encryptString` and `decryptString` for those keys. Write a migration that re-saves existing values encrypted.
- [ ] **T4.6** In Filament settings, show secrets as password fields that never echo the stored value (placeholder `••••`, empty input means unchanged).

## 3. Tenant isolation of integration services (P1)

- `PaymobService`, `KashierService`, `PaymentGatewayFactory`, `PaymentProcessingConfig`, `CartEngineConfig`, `CouponsPromotionsConfig` and `OrderPipelineRegistry` are container **singletons** filled from the first tenant's settings.
- In queue workers (for example `SendOrderToPosJob`) and in any future Octane deployment, tenant B would use tenant A's gateway keys and POS config.
- [ ] **T4.7** Add a `TenancyInitialized` / `TenancyEnded` listener that calls `app()->forgetInstance()` for a list of tenant-scoped services that each plugin declares (for example a `tenant_scoped` array in `plugin.json`, or a `TenantScoped` marker interface).
- [ ] **T4.8** Test: boot tenant A, then tenant B in the same process, and assert `app(PaymobService::class)` uses B's secret.

## 4. Rate limiting (P1)

| Endpoint | Limit |
|---|---|
| `/api/webhooks/*` | `throttle:120,1` per IP (gateways retry in bursts) |
| `/api/order-status` | `throttle:60,1` per IP plus branch |
| `/orders/{id}/payment/callback` | `throttle:20,1` per IP |
| `/auth/google/*` | `throttle:10,1` per IP |

- [ ] **T4.9** Define named limiters in `AppServiceProvider` (`RateLimiter::for('integration-webhook', ...)`) and apply them in each plugin's route file.

## 5. Monitoring (P2)

- [ ] **T4.10** Count signature failures per tenant and gateway. Tell the tenant admin when there are more than 10 in 10 minutes (it is either an attack or a misconfigured secret).
- [ ] **T4.11** Add a weekly job that reports orders still `payment_status=pending` 24 hours after a callback hit, which means a webhook was missed.

## 6. Test suite prerequisites

The full test suite is currently unreliable (shared SQLite tenant database file, `migrations already exists` cascade; see the 2026-10-25 review log). Security tests must run reliably in CI:
- [ ] **T4.12** Give each process its own tenant test database (`database/tenanttest_{pid}`), or use in-memory SQLite for the tenant connection.
- [ ] **T4.13** Put the security tests in a `Security` test group so CI can run `php artisan test --group=security` on every PR.
