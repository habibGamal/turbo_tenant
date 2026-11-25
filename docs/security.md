# Security controls

This page summarizes the security controls currently implemented in Turbo Tenant.

## Multi-tenancy and plugin isolation

- **Database and storage isolation:** [`stancl/tenancy`](../architecture.md#tenant-identification-and-plugin-boot) resolves the active store from the request host, switches the default DB connection (`tenant<id>`), storage roots (`storage/tenant<id>`), cache tags, and queue context (`QueueTenancyBootstrapper`), and blocks tenant routes on central domains (`PreventAccessFromCentralDomains`).
- **Per-tenant plugin gating:** plugin routes wrapped in [`PluginRoutes::tenant`](../../app/Core/Plugins/Routing/PluginRoutes.php) enforce `plugin.active:<id>` ([`EnsurePluginActive`](../../app/Core/Plugins/Http/Middleware/EnsurePluginActive.php)), returning `404` when inactive. Listeners are wrapped in [`ActivationGatedListener`](../../app/Core/Plugins/Listeners/ActivationGatedListener.php).

## Authentication and sessions

- **Storefront auth:** Sanctum stateful session cookies (`SESSION_DOMAIN=null` so cookies stay scoped to each store domain) with CSRF verification on mutating requests (`/sanctum/csrf-cookie`).
- **Session regeneration:** login and registration regenerate the session ID ([`AuthController`](../../app/Http/Controllers/Api/AuthController.php), [`GoogleAuthController`](../../app/Http/Controllers/Auth/GoogleAuthController.php), [`GoogleNativeAuthController`](../../app/Http/Controllers/Api/GoogleNativeAuthController.php)); logout invalidates the session and regenerates the CSRF token.
- **Rate limits on auth endpoints:** password reset (`forgot-password`, `reset-password`), email verification, and password update are throttled at `6/min`; native Google sign-in is throttled at `10/min` ([routes/tenant.php](../../routes/tenant.php), [routes/auth.php](../../routes/auth.php)).
- **Google ID token verification:** [`GoogleIdTokenVerifierService`](../../app/Services/Auth/GoogleIdTokenVerifierService.php) verifies signature, `aud`/`azp` against `GOOGLE_ALLOWED_CLIENT_IDS`, `iss`, `exp`, and `email_verified === true`. Existing password accounts without `google_id` return `409 link_required` instead of auto-linking.
- **Admin panel access:** `/admin` checks `users.is_admin` in [`User::canAccessPanel`](../../app/Models/User.php#L52); `/central` accepts users in the central `users` table. Note: `VerifyCsrfToken` and MFA are currently commented out in [`AdminPanelProvider`](../../app/Providers/Filament/AdminPanelProvider.php).

## Authorization and guest tracking

- **Customer orders:** [`OrderController`](../../app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderController.php) scopes `index` and `show` queries by `where('user_id', $request->user()->id)` and hides internal payment/POS fields on the `Order` model.
- **Addresses:** [`AddressPolicy`](../../app/Plugins/LocationsDelivery/src/Policies/AddressPolicy.php) restricts view/update/delete to the owning user.
- **Payment status & Kashier config:** [`AuthorizesOrderPayment`](../../app/Plugins/PaymentProcessing/src/Http/Requests/Concerns/AuthorizesOrderPayment.php) allows access only to the order's authenticated owner or a session holding the order ID in `guest_order_ids`.
- **Guest order tracking:** [`GuestCheckoutApiController::track`](../../app/Plugins/GuestCheckout/src/Http/Controllers/Api/GuestCheckoutApiController.php) requires `order_number` plus either an HMAC-SHA256 `token` ([`GuestTrackingTokenService`](../../app/Plugins/GuestCheckout/src/Services/GuestTrackingTokenService.php), verified via `hash_equals` using `APP_KEY`) or `phone` (+ `phone_country_code`), returning `404` on mismatch.

## Payment callbacks and webhooks

- **Read-only browser callback:** [`PaymentCallbackService`](../../app/Plugins/PaymentProcessing/src/Services/PaymentCallbackService.php) only reads `payment_status` from the database and never changes order or payment state.
- **Fail-closed webhooks:** [`PaymobWebhookController`](../../app/Plugins/PaymentProcessing/src/Http/Controllers/PaymobWebhookController.php) (reads `hmac` from query string only) and [`KashierWebhookController`](../../app/Plugins/PaymentProcessing/src/Http/Controllers/KashierWebhookController.php) (reads `X-Kashier-Signature` header) select the gateway from the route, reject missing signatures (`400`), and return `503` if the gateway secret is unconfigured (`PaymentGatewayNotConfiguredException`).
- **Verification and idempotency:** [`PaymentWebhookService`](../../app/Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php) verifies the HMAC (`hash_equals`, with Kashier requiring `merchantOrderId`, `amount`, `currency`, `status`, and `transactionId` in `signatureKeys`), deduplicates by `payment_events (gateway, transaction_id)`, checks exact amount in cents and currency against the order, and applies forward-only transitions via [`PaymentStateMachine`](../../app/Plugins/PaymentProcessing/src/Services/PaymentStateMachine.php).
- **CSRF exemption:** `api/webhooks/*` is excluded from CSRF in [bootstrap/app.php](../../bootstrap/app.php).

## POS integration

- **Inbound order status (`POST /api/order-status`):** CSRF-exempt, authenticated by [`VerifyIntegrationToken`](../../app/Plugins/OrderManagement/src/Http/Middleware/VerifyIntegrationToken.php) (`Authorization: Bearer <token>`, `hash_equals`), throttled at `60/min`, restricted to forward-only transitions via [`OrderStatusTransitions`](../../app/Plugins/OrderManagement/src/Services/OrderStatusTransitions.php) (`409` otherwise), and logged in `order_status_events`.
- **Outbound SSRF guard:** [`POSApiClient`](../../app/Plugins/PosIntegration/src/Services/POS/POSApiClient.php) checks every branch URL with [`PublicEndpointGuard`](../../app/Support/Network/PublicEndpointGuard.php), which enforces `http`/`https`, rejects userinfo, blocks `localhost` and private/loopback/link-local/reserved IPv4 and IPv6 ranges (including DNS resolution and IPv4-mapped IPv6), and disables redirects (`allow_redirects => false`) unless `NETWORK_ALLOW_PRIVATE_HOSTS=true`.
- **Outbound credentials and TLS:** requests send `branch.pos_api_key` (or `products_repo_api_key`) in `X-Api-Key`. TLS peer verification is intentionally disabled (`verify => false`) on branch POS calls because branch servers use self-signed certificates.

## Logging, error handling, and secrets

- **Log redaction:** [`RedactSensitiveContextProcessor`](../../app/Logging/RedactSensitiveContextProcessor.php) masks keys containing sensitive segments (`password`, `passwd`, `token`, `secret`, `hmac`, `signature`, `apikey`, `authorization`, `pan`, `card`, `cvv`) with `[REDACTED]`. Webhook controllers and `POSApiClient` log metadata without bodies or PII.
- **API error envelope:** [bootstrap/app.php](../../bootstrap/app.php) formats `api/*` exceptions into `{status:"error",message,errors?}` and avoids leaking model class names on `404`.
- **Secrets at rest:** `meta_capi_access_token` (`Crypt::encryptString`) and `branches.pos_api_key` (`encrypted` cast) are encrypted at rest. Other store secrets (Paymob/Kashier keys, Google client secret, SMTP password, POS master API key, and integration token) are stored as plain text in tenant `settings`.
- **Meta endpoints:** `POST /api/meta/events` is throttled (`120/min`) and restricted to an event/key allow-list in [`RelayEventRequest`](../../app/Plugins/MetaMarketing/src/Http/Requests/RelayEventRequest.php); `/meta/catalog/{token}.*` compares the URL token with `hash_equals`.
- **CMS pages:** [`ThemedCmsPages`](../../resources/js/themes/default/plugins/cms-pages/ThemedCmsPages.tsx) renders admin-authored HTML with `dangerouslySetInnerHTML` without client-side sanitization (trusted admin input).
