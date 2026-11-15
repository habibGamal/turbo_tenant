# Phase 2: POS Integration (Inbound status updates + Outbound order push)

> **Plugins:** `order-management` (inbound route), `pos-integration` (outbound client)
> **Covers:** I-4 `POST /api/order-status`, O-1 `POSApiClient`
> **Counterparty:** Larament POS (see `CREATE_ORDER_API_DOC.md`); each branch runs its own POS at `branches.link`

---

## 1. Findings

### 1.1 CRITICAL: `POST /api/order-status` has no authentication
- Route: `app/Plugins/OrderManagement/routes/tenant.php:18`, CSRF-exempt (`bootstrap/app.php:26`), deliberately outside `plugin.active`.
- `OrderStatusUpdateRequest::authorize()` returns `true`. The controller sets `status` to whatever is sent, for the order matching `orderNumber`.
- Order numbers (`ORD-XXXXXXXX`) are shown to customers on receipts and the tracking page, so a customer can mark their own order `completed` or `cancelled`, or set any status on an order number they know or saw.
- No rate limit, no audit trail, no transition rules (a `completed` order can go back to `pending`).
- `catch (Exception)` hides failures and returns 400 with no detail, so the POS side can't tell a rejection from a bug.

### 1.2 ACCEPTED: outbound POS calls skip TLS verification
- `POSApiClient.php:28`, `:64` and `:105` use `Http::withOptions(['verify' => false])`. This is **intentional** (branch POS servers use self-signed certificates) and the owner accepted it on 2026-10-28. Do not change it. The HMAC signing in §2 still protects message integrity.

### 1.3 HIGH: SSRF through the tenant-controlled `branches.link`
- `getBranchUrl()` builds the URL from `$branch->link`, which tenant admins edit freely in `BranchForm` (`Textarea::make('link')`, no validation).
- A tenant admin, who is a customer of the SaaS and not trusted by the platform, can point it at `http://169.254.169.254/...`, `http://localhost:6379` or internal services. The server makes the request, and `getShiftId` / `canAcceptOrder` responses appear in the admin UI and logs.

### 1.4 MEDIUM: no authentication on outbound calls either
- The POS API is documented as "Authentication: None". Anyone who finds a branch's POS URL can push fake orders straight into the kitchen. This is the POS project's problem, but the fix has to be agreed between the two sides (§2).

### 1.5 MEDIUM: full payloads logged
- `POSApiClient::sendOrder` logs `url` and the full `payload` (customer PII) on every order, plus the full POS response.

---

## 2. Target design: one shared secret per branch, used both ways

| Item | Value |
|---|---|
| Secret | 32-byte random `pos_shared_secret`, **per branch**, generated in Filament, stored with `encrypted` cast on `branches` (or a `branch_pos_credentials` table owned by `pos-integration`) |
| Request signature | `X-Turbo-Timestamp: <unix>` + `X-Turbo-Signature: hex(hmac_sha256(secret, timestamp + "." + raw_body))` |
| Replay window | reject if `abs(now - timestamp) > 300s`; cache `signature` for 10 min to block exact replays |
| Direction | **Outbound** (us → POS): we sign. **Inbound** (POS → us): POS signs, we verify |
| Branch identification inbound | `X-Turbo-Branch: <branch_id>` header → load that branch's secret → order must belong to that branch |

The POS team (Larament) has to implement verification of our signature and signing of their callbacks. Until they do, keep the inbound endpoint behind T2.1's interim token.

---

## 3. Tasks

### P0: inbound `/api/order-status`
- [ ] **T2.1** (interim, ships this week) Require a bearer token. Add `pos-integration.inbound_token` to tenant settings (encrypted, generated once, shown in Filament). New middleware `VerifyPosInboundToken` uses `hash_equals`, returns 401 without one, and is applied to the route. Give the token to the POS team.
- [ ] **T2.2** Move the route from `order-management` into `pos-integration`. The POS is the only caller, and the plugin manifest already depends on order-management. Keep it reachable while pos-integration is deactivated **only** if in-flight orders need it; otherwise put it behind `plugin.active:pos-integration`.
- [ ] **T2.3** Add `throttle:60,1` on the route, keyed by IP plus branch.
- [ ] **T2.4** Status transition table (`OrderStatusTransitions`): `pending → processing → out_for_delivery → completed`, and `* → cancelled` only from `pending|processing`. A disallowed transition returns 409 with a reason. The POS must never move an order backwards.
- [ ] **T2.5** Audit: `order_status_events` (`order_id`, `from`, `to`, `source=pos|admin|system`, `branch_id`, `ip`, `created_at`).
- [ ] **T2.6** Tests: no token → 401; wrong token → 401; valid token with a disallowed transition → 409; valid → 200 plus an audit row; customer session cookie with no token → 401.

### P1: full HMAC + outbound hardening
- [ ] **T2.7** Replace T2.1 with the per-branch HMAC scheme in §2, coordinated with the POS team. Accept both for one release and log which one each request used.
- [x] ~~**T2.8** Remove `verify => false`~~: dropped. TLS skip is accepted (§1.2).
- [ ] **T2.9** SSRF guard on `branches.link`:
  - Filament validation: `url`, scheme `https` (allow `http` only with a platform-level flag), no userinfo, no IP literals in private, loopback or link-local ranges.
  - At request time, resolve DNS and reject private, loopback and link-local addresses (`169.254.0.0/16`, `10/8`, `172.16/12`, `192.168/16`, `127/8`, `::1`, `fc00::/7`). This also stops DNS rebinding. Disable redirects (`allow_redirects => false`).
  - Keep the 10s timeout and add `connect_timeout => 3`.
- [ ] **T2.10** Sign outbound requests (`X-Turbo-Signature`) so the POS can reject forged orders, once the POS supports it.
- [ ] **T2.11** Log hygiene: log `branch_id, order_number, status, duration_ms` only, with no payload or response body.

### P2
- [ ] **T2.12** Admin action "Rotate POS secret", which shows the new secret once and keeps the old one valid for 24 hours.
- [ ] **T2.13** Health check: a Filament widget showing, per branch, the last successful signed callback and the failure count.

---

## 4. Files touched

- `app/Plugins/OrderManagement/routes/tenant.php` (remove the route) → `app/Plugins/PosIntegration/routes/tenant.php` (add the route)
- `app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderStatusController.php` → move to PosIntegration
- `app/Plugins/OrderManagement/src/Http/Requests/OrderStatusUpdateRequest.php` and the host shim `app/Http/Requests/OrderStatusUpdateRequest.php` (delete the shim)
- new `app/Plugins/PosIntegration/src/Http/Middleware/VerifyPosSignature.php`
- `app/Plugins/PosIntegration/src/Services/POS/POSApiClient.php`
- `app/Plugins/LocationsDelivery/src/Filament/Resources/Branches/Schemas/BranchForm.php` (link validation)
- `bootstrap/app.php` (the CSRF exception stays, but the route path changes if it moves)
