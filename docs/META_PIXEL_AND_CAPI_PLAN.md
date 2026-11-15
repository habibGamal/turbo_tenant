# Meta Pixel, Conversions API & Product Catalog plan

Status: **implemented (uncommitted)** — plugin `app/Plugins/MetaMarketing` (`meta-marketing`) + storefront
plugin `resources/js/plugins/meta-marketing`. Delegation log: `.plan/review/meta-marketing-progress.md`.
Date: 2026-11-05 (branch `refactoring`) — rev 2

Deploy / enable per tenant:

1. `composer dump-autoload` and `php artisan plugin:discover` (new plugin; run `plugin:clear` if the manifest cache exists).
2. `php artisan tenant:plugin:activate {tenant} meta-marketing` (activates and migrates
   `meta_order_tracking`, `meta_capi_events`); for already-active tenants use
   `php artisan tenants:plugin:migrate --plugin=meta-marketing`.
3. A queue worker must run (Purchase/relay events are queued jobs).
4. Admin → Settings → "تسويق ميتا (Meta)": Pixel ID, Conversions API toggle + access token (stored encrypted),
   optional Test Event Code, catalog toggle (feed token is generated) and brand.
5. Admin → "تشخيص تكامل ميتا": token check, test event, delivery log, catalog URLs/check, ID consistency check.
6. Commerce Manager: add the CSV (or XML) feed URL as a scheduled data feed, plus the `.ar.csv` URL as an Arabic
   language override feed; connect the Pixel/dataset to the catalog. Then follow the checklist in §8.3.

Decisions taken (rev 2):

- COD Purchase goes to Meta **as soon as the order is placed** (not when delivered).
- Phase 2 (server-side funnel events) is **in scope**.
- A **Meta product catalog** + Meta-compatible feed export endpoint is **in scope**.
- A **test & confirmation toolkit** (admin diagnostics + automated tests + acceptance
  checklist) is **in scope**.

---

## 1. Review of the current Pixel implementation

### 1.1 Blocker: the Pixel is never initialized

- `resources/js/components/InitPixel.tsx` exists but **nothing mounts it**. It was removed from
  `resources/js/app.tsx` in `facdac3` ("theme adapter architecture") and never re-added.
- `react-facebook-pixel`'s `track()` checks an internal "initialized" flag and silently returns
  when `init()` was never called (it only logs when `debug: true`). So **every event below is a
  no-op in production today**: PageView, ViewContent, AddToCart, AddToWishlist,
  InitiateCheckout, Purchase. No `fbevents.js` is loaded at all.
- The `try { ReactPixel.track(...) } catch {}` wrappers give a false sense of safety; nothing
  throws, it just does nothing.
- Backend still exposes the ID: `StoreSettingsBootstrapContributor` → `settings.facebook_app_id`,
  but no frontend code reads it.
- The admin field is labelled **"Facebook App ID"** (`StoreIdentitySettingsSection.php:64`).
  A Pixel/Dataset ID is not an App ID; merchants will paste the wrong value.

### 1.2 Event-by-event findings

| Event | Where | Placement | Parameters | Verdict |
|---|---|---|---|---|
| **PageView** | `InitPixel.tsx` | Fired once on init only. This is a react-router SPA, so client-side navigations never send PageView. | — | ❌ wrong placement |
| **ViewContent** | `ThemedProductShow.tsx:95-109` | `useEffect` deps are `[selectedVariant, product]` but the payload ignores the variant → duplicate identical ViewContent on every variant click, and again whenever `product` re-memos after a refetch. | `content_ids:[product.id]` numeric (Meta expects strings), value = default price. No `content_name`/`content_category`. | ⚠️ duplicates |
| **AddToCart** (PDP) | `ThemedProductShow.tsx:194-218` | Correct (on mutation success). | `value` = **unit price**, not unit × quantity; ignores extras and weight multiplier; `variant.price` bypasses `getEffectivePrice` (discount ignored); `content_ids` switches to **variant.id** while ViewContent/InitiateCheckout/Purchase use **product_id** → IDs don't match across the funnel. | ❌ wrong value / IDs |
| **AddToCart** (options modal) | `ProductOptionsModal.tsx:55-67` | Correct. | Unit price only; no variant/extras/weight. | ❌ wrong value |
| **AddToCart** (quick add) | `ProductCard.tsx:102`, `MenuItemRow.tsx:78` | **Missing**: direct adds via `useAddToCart` fire no event. Likely the most-used add path on the menu page. | — | ❌ missing |
| **AddToWishlist** | `ThemedFavoriteButton.tsx:36-46` (product card only) | Fires before the toggle is confirmed. PDP heart (`handleToggleFavorite` → `useFavorites`) fires nothing. | No `content_type`; `value` falls back to `0` when `productPrice` isn't passed. | ⚠️ partial |
| **InitiateCheckout** | `CartSummary.tsx:70-83` (button click) | Only fires from the cart button; direct visits to `/checkout`, "buy now" and redirects after login miss it. | `num_items` = number of **lines**, not units; `cart.total` excludes delivery. | ⚠️ wrong placement |
| **Purchase** | `Checkout.tsx:355-365` | Fires right after `placeOrder` succeeds, **before** the online-payment redirect. Card/wallet orders that are abandoned or fail at the gateway are reported as purchases; the payment callback page fires nothing. | `value` = client-computed `totalAmount` (can diverge from server `order.total`); no `order_id`; no `eventID` (no dedup possible); `contents` lack `item_price`; `num_items` = line count; `content_ids` = product_id (mismatch with AddToCart variant IDs). | ❌ wrong placement + params |
| **Search** | `ThemedMenuPage.tsx` (`?search=`) | Missing. | — | ➕ add |
| **CompleteRegistration** | `pages/Register.tsx` | Missing. | — | ➕ add |
| **AddPaymentInfo** | Checkout payment-method step | Missing (optional). | — | ➕ optional |

### 1.3 Cross-cutting issues

- **No `eventID`** anywhere. `ReactPixel.track(name, data)` only forwards two args, so even if
  we wanted dedup we'd have to call `ReactPixel.fbq('track', name, data, { eventID })`.
- **No advanced matching**: `init(fbID, undefined, …)`. Logged-in users' email/phone are known
  and could be passed to `init` (Pixel hashes them client-side).
- **Tracking scattered across 5 theme files**, each importing `react-facebook-pixel` directly.
  A second theme would have to re-implement all of it. Tracking belongs in a shared module
  (or a frontend plugin), called from shared hooks such as `useAddToCart`.
- `InitExpo.tsx` imports `ReactPixel` but doesn't use it.
- `.github/docs/FACEBOOK_PIXEL_IMPLEMENTATION.md` describes the old Inertia app
  (`ProductActions.tsx`, `router.post`, hardcoded `EGP`) and is stale.

---

## 2. Content ID scheme (shared by Pixel, CAPI and the catalog feed)

Because a catalog is now in scope, event `content_ids` **must equal the feed's `id` column**,
otherwise Meta can't match events to catalog items (no dynamic ads, low match rate).

| Catalog item | Feed `id` | Feed `item_group_id` |
|---|---|---|
| Product **without** variants | `"{product_id}"` | — |
| Product **with** variants (one row per variant) | `"{product_id}-{variant_id}"` | `"{product_id}"` |

Event usage:

- AddToCart / InitiateCheckout / Purchase: the exact item → `"{p}"` or `"{p}-{v}"`,
  `content_type: "product"`.
- ViewContent on a product with variants and none selected yet: `content_ids: ["{p}"]`,
  `content_type: "product_group"` (matches `item_group_id`). Without variants → `"{p}"`,
  `"product"`.
- Weight options and extras are **not** catalog variants; they only change `item_price`/`value`.

One implementation per side, never hand-built strings:

- Backend: `MetaContentId::for(int $productId, ?int $variantId): string`, used by the feed,
  CAPI payloads and the placement-response contributor.
- Frontend: `metaContentId(productId, variantId?)` in the tracking module.
- A contract test asserts both produce identical IDs (see §8).

---

## 3. Target event map

| Event | Trigger | Key params | eventID | Browser | CAPI |
|---|---|---|---|---|---|
| PageView | every route change (`useLocation`) | — | `uuid` | ✅ | phase 2 |
| ViewContent | PDP, once per `product.id` (+ once per newly selected variant, as `product`) | ids, value = effective price, currency, content_name, content_category | `uuid` | ✅ | phase 2 |
| Search | menu search (debounced, committed query) | `search_string` | `uuid` | ✅ | phase 2 |
| AddToWishlist | after favorite added (card + PDP) | ids, value, currency, content_type | `uuid` | ✅ | phase 2 |
| AddToCart | `useAddToCart.onSuccess` (covers PDP, modal, card, menu row) | ids, contents[{id,quantity,item_price}], value = line total of added qty | `uuid` | ✅ | phase 2 |
| InitiateCheckout | Checkout page mount, once per cart signature | ids, contents, value = cart subtotal, `num_items` = Σ qty | `uuid` | ✅ | phase 2 |
| AddPaymentInfo | (optional) place-order click with online method | value, currency | `uuid` | ✅ | phase 2 |
| CompleteRegistration | Register success | `status: true` | `uuid` | ✅ | phase 2 |
| **Purchase** | **COD: at placement.** Online: when payment completes. | server `order.total`, currency, `order_id`, contents with `item_price`, `num_items` = Σ qty | **`purchase.{order_id}`** (deterministic) | ✅ | ✅ **phase 1** |

---

## 4. Phase 0 — Fix the browser Pixel (frontend only)

1. **Shared tracking module** `resources/js/lib/meta-pixel.ts` (or a `meta-pixel` frontend plugin
   registered in `plugins/registry.ts`):
   - `initPixel(pixelId, advancedMatching?)`, `trackPageView()`, `track(event, data, eventId?)`
     using `ReactPixel.fbq('track', event, data, { eventID })`.
   - `newEventId()` (crypto.randomUUID), `metaContentId()`, payload builders
     (`toContents(cartItems)`, number coercion, Σ quantity).
   - No-op cleanly when no pixel ID. Debug mode (see §8.1) logs every call.
2. **Mount init + route PageView**: a `<MetaPixelTracker/>` inside the router (needs
   `useLocation`) that reads `settings.facebook_app_id` from `TenantContext`, inits once, and
   fires PageView on each `location.pathname + search` change (no double fire on first load).
   Pass `{ em, ph, external_id }` for logged-in users from `AuthContext`.
3. **ViewContent**: deps `[product?.id]` (+ variant change as its own event), catalog IDs,
   effective price, `content_name`, `content_category`.
4. **AddToCart**: move into `useAddToCart` `onSuccess` with a `track?: boolean` option
   (default `true`); `ThemedCart`'s undo-restore passes `track: false`. Value = added quantity ×
   (unit price + extras) from the returned cart line (verify `CartItem.price` /
   `extras_total` / `weight_multiplier` semantics before coding). Remove the three per-component
   calls.
5. **AddToWishlist**: add `content_type`, fire on both card and PDP favorite actions, only on add.
6. **InitiateCheckout**: move from `CartSummary` button to `Checkout.tsx` mount (guard with a
   ref + cart signature so re-renders don't refire).
7. **Purchase**:
   - Use values from the placement response (see 5.6) — server total, `order_id`, `event_id`.
   - COD / `requires_payment=false`: fire immediately after placement.
   - Online: **don't fire before redirect**. Stash the purchase payload in `sessionStorage`
     keyed by order id; `ThemedPaymentCallback` fires it once when `usePaymentStatus` reports
     `isPaid`, then deletes it (guard against refresh double-fire).
8. **Search** and **CompleteRegistration**: add.
9. **Admin label**: rename "Facebook App ID" → "Meta Pixel ID" with helper text. Keep the
   `facebook_app_id` key (no data migration) or add `meta_pixel_id` with a fallback read.
10. Delete unused `ReactPixel` import in `InitExpo.tsx`; rewrite the stale
    `.github/docs/FACEBOOK_PIXEL_IMPLEMENTATION.md`.

---

## 5. Phase 1 — Conversions API (server-side Purchase)

### 5.1 Shape: a new backend plugin `MetaMarketing` (`id: meta-marketing`)

One plugin owns Pixel settings, CAPI and the catalog feed (they share the pixel ID, token,
content-ID helper and diagnostics page). Follows the existing plugin pattern
(`PushNotifications` / `PosIntegration`):
`app/Plugins/MetaMarketing/{plugin.json, MetaMarketingServiceProvider.php, database/, routes/, src/}`,
dependencies `order-management`, `catalog-products`. Scaffold with `MakePluginCommand`.

### 5.2 Settings (per tenant)

| Key | Notes |
|---|---|
| `facebook_app_id` (existing) → Pixel/Dataset ID | shared with browser Pixel |
| `meta_capi_enabled` | toggle |
| `meta_capi_access_token` | `->password()`, **encrypted at rest** (`Crypt::encryptString` on save, decrypt on read). Note: no settings are encrypted today (Paymob keys included) — worth a separate fix. Never expose in bootstrap. |
| `meta_capi_test_event_code` | optional, for Events Manager → Test Events |
| `meta_catalog_enabled` | toggle for the feed endpoint |
| `meta_catalog_feed_token` | random 40-char token, "regenerate" action |
| `meta_catalog_brand` | defaults to store name (Meta requires `brand`, products have none) |
| `meta_debug_mode` | storefront Pixel debug logging (see §8.1) |

Graph API version: config value (`config/services.php` → `meta.graph_version`); check the
current version in Meta's docs at implementation time rather than hardcoding.

### 5.3 Capture request context at order time

For online payments the Purchase is sent from the gateway callback/webhook, where the
customer's cookies, IP and user agent are **not available**. So capture them when the order
is placed:

- Migration (tenant, inside the plugin's `database/`; write it, don't run it against tenants
  as part of this work): `meta_order_tracking` — `order_id`, `fbp`, `fbc`, `client_ip`,
  `user_agent`, `event_source_url`, `purchase_event_id`, `purchase_sent_at`, timestamps.
- Sync listener on `OrderCreated` → `CaptureOrderTrackingContext`:
  - `_fbp` / `_fbc` cookies; if `_fbc` is absent but the landing URL had `fbclid`, build
    `fb.1.{ms_timestamp}.{fbclid}` (Pixel sets `_fbc` itself once it's actually initialized).
  - **Verify cookie encryption**: if these routes run Laravel's `EncryptCookies`, `_fbp`/`_fbc`
    will be nulled (they aren't Laravel-encrypted). Add
    `$middleware->encryptCookies(except: ['_fbp', '_fbc'])` in `bootstrap/app.php` if so.
  - `request()->ip()` — confirm trusted proxies are set so it's the client IP, not the LB.
  - Skip `OrderType::POS` orders (not web conversions).

### 5.4 When Purchase is sent

| Payment | Event listened to | When it fires | Result |
|---|---|---|---|
| **COD / offline** | `OrderFinalized` | Same HTTP request as placement — `PlaceOrderService::placeOrder()` calls `finalizeOfflineOrder()` (sets status `pending`) and dispatches it immediately. It does **not** mean delivered/completed. | Sent **at placement** ✅ |
| Online (card/wallet) | `OrderPaymentCompleted` | Only on transition into `completed` (`PaymentStateMachine`). | Sent when paid; abandoned/failed payments never counted |

Note on the name: `OrderFinalized` means "placement finalized", not "order finished". If this
is confusing, the listener can instead use `OrderCreated` filtered to
`! paymentMethod->requiresOnlinePayment()` — same timing. Either way no listener waits for
delivery.

Cancelled COD orders: Meta has no "refund" event; they stay counted. If that matters, a later
option is sending a custom `OrderCancelled` event for reporting only.

### 5.5 Sending

- Listeners queued (`ShouldQueue`, `afterCommit`; tenancy is carried by
  `QueueTenancyBootstrapper`, queue = `database`). Queue latency is seconds, so COD purchases
  still arrive "at placement".
- Job `SendMetaPurchaseEvent`: `tries=5`, exponential backoff, idempotent via
  `purchase_sent_at`; deterministic `event_id = "purchase.{order_id}"` so a retry or the
  browser event dedups at Meta anyway. Every attempt is written to the delivery log (§8.1).
  Failures never touch the order flow.
- `POST https://graph.facebook.com/{version}/{pixel_id}/events` with `access_token`, optional
  `test_event_code`, body:

```json
{
  "data": [{
    "event_name": "Purchase",
    "event_time": 1762370000,
    "event_id": "purchase.123",
    "action_source": "website",
    "event_source_url": "https://tenant.example/checkout",
    "user_data": {
      "em": ["<sha256>"], "ph": ["<sha256>"],
      "fn": ["<sha256>"], "ln": ["<sha256>"],
      "ct": ["<sha256>"], "country": ["<sha256 of 'eg'>"],
      "external_id": ["<sha256>"],
      "client_ip_address": "…", "client_user_agent": "…",
      "fbp": "fb.1.…", "fbc": "fb.1.…"
    },
    "custom_data": {
      "currency": "EGP", "value": 450.0, "order_id": "ORD-…",
      "content_type": "product", "content_ids": ["12", "40-7"],
      "contents": [{ "id": "12", "quantity": 2, "item_price": 150.0 }],
      "num_items": 3
    }
  }]
}
```

### 5.6 `UserDataBuilder` (normalize, then SHA-256)

- `em`: trim + lowercase. `ph`: digits only, E.164 without `+`; for Egyptian local numbers strip
  the leading `0` and prefix `20` (use the store's country). `fn`/`ln`: split customer name,
  lowercase. `ct`: area/governorate city name lowercase, no spaces. `country`: `eg`.
  `external_id`: `user_id` or `guest_user_id` (prefixed, e.g. `u:12` / `g:7`).
- Sources: `Order::customerEmail()` / `customer_phone` snapshot / user / guest data / address.
- **Not hashed**: `client_ip_address`, `client_user_agent`, `fbp`, `fbc`.
- `custom_data` from `order.total`, order items (`product_id`, `variant_id` → `MetaContentId`,
  `quantity`, `unit_price`). `event_time` = placement time (COD) or payment time (online);
  Meta rejects events older than 7 days.

### 5.7 Return tracking data to the browser (dedup)

Implement an `OrderPlacementResponseContributor` (existing extension point, tag
`order.placement.response`) that adds:

```json
"tracking": { "purchase_event_id": "purchase.123", "value": 450.0, "currency": "EGP",
              "content_ids": [...], "contents": [...], "num_items": 3 }
```

The browser Purchase (§4 step 7) uses these exact values and `eventID`, so Meta
deduplicates browser + server into one conversion.

---

## 6. Phase 2 — Server-side funnel events

For PageView, ViewContent, Search, AddToWishlist, AddToCart, InitiateCheckout,
AddPaymentInfo, CompleteRegistration:

- Relay endpoint `POST /api/meta/events` in the plugin:
  - Whitelist of event names; body `{ event_name, event_id, custom_data, event_source_url }`.
  - Server attaches IP, UA, `_fbp`/`_fbc`, hashed user data for logged-in customers.
  - Throttled per IP (e.g. 60/min), rejects payloads > N KB, validates `content_ids` format.
  - Queued dispatch; batched (CAPI accepts up to 1000 events per request), flush every few
    seconds or N events.
- The frontend tracking module sends each event to Pixel **and** the relay with the same
  `event_id` (fire-and-forget, `navigator.sendBeacon` where possible so navigation doesn't
  cancel it).
- Values for these events are informational (not revenue), so client-supplied values are
  acceptable; Purchase stays server-authoritative.
- Delivery log keeps only failures + a sample for these high-volume events, with pruning.
- Optionally make PageView server-side configurable off (highest volume, lowest value).

---

## 7. Phase 3 — Meta product catalog & feed export

### 7.1 What the data model gives us

`Product`: `name`, `name_ar`, `description` (no Arabic description), `image` (single),
`base_price`, `price_after_discount`, `category_id`, `is_active`, `sell_by_weight`.
`ProductVariant`: `name`, `name_ar`, `price` (absolute price), `is_available`.
No slug, SKU, brand, stock count or gallery. Storefront URL: `/products/{id}`.

### 7.2 Endpoint

- Tenant route: `GET /meta/catalog/{token}.csv` (primary) and `GET /meta/catalog/{token}.xml`
  (RSS 2.0 with `g:` namespace, optional — same row builder).
- Token must match `meta_catalog_feed_token` (constant-time compare), else 404. Disabled
  setting → 404. Token in the path because Meta's scheduled fetch takes a plain URL.
- Generated on every fetch (`chunkById` over products with `variants`, `category`), UTF-8,
  `text/csv`. **No Laravel cache**: tenancy's `CacheTenancyBootstrapper` needs a taggable store and the app
  runs on the `database` store (a first version cached the feed and broke product saves and feed requests).
- Meta setup: Commerce Manager → Catalog → Data sources → Data feed → scheduled fetch from the
  URL (hourly or daily). Then associate the Pixel/dataset with the catalog in Events Manager.

### 7.3 Column mapping (one row per product, or per variant)

| Meta field | Source |
|---|---|
| `id` | `MetaContentId::for(product, variant)` (§2) |
| `item_group_id` | `"{product_id}"` when the product has variants |
| `title` | `name` (+ ` - {variant.name}`), ≤ 200 chars (aim ≤ 65) |
| `description` | `description`, HTML stripped; fallback to title (field is required) |
| `availability` | `in stock` if `is_active` && (no variant or `variant.is_available`), else `out of stock` |
| `condition` | `new` |
| `price` | `"{base or variant price} {CURRENCY}"`, 2 decimals, e.g. `150.00 EGP` |
| `sale_price` | `price_after_discount` when an active discount applies (`hasActiveDiscount` rules mirrored server-side) |
| `link` | absolute `https://{tenant-domain}/products/{id}` |
| `image_link` | absolute `Storage::url(image)`; prefer the largest MediaOptimizer rendition (Meta wants ≥ 500×500) |
| `brand` | `meta_catalog_brand` setting (defaults to store name) |
| `product_type` | category name |
| `fb_product_category` / `google_product_category` | optional per-category mapping (setting on Category); skip in v1 |
| `custom_label_0` | `sell_by_weight ? "by_weight" : ""` (lets ads filter weight-sold items) |

Edge cases to settle during implementation: variant price vs product discount (does
`price_after_discount` apply to variants?), weight-sold products (catalog shows the base unit
price), products with no image (skip row + report in validation), combo packages (excluded in
v1).

### 7.4 Localization (optional, v1.1)

Arabic language override feed `GET /meta/catalog/{token}.ar.csv` with columns `id`,
`override` (`ar_XX`), `title` (`name_ar`), `link` (Arabic locale URL if one exists). No Arabic
description exists today.

### 7.5 Real-time updates (later, optional)

Catalog Batch API (`/{catalog_id}/items_batch`) on product save for instant price/stock
changes. Needs `meta_catalog_id` + a token with catalog-management permission. Scheduled feed
fetch is enough for v1.

---

## 8. Testing & confirmation toolkit

Three layers: an in-admin diagnostics page (so the merchant/you can confirm live), automated
tests (so regressions are caught), and a one-time acceptance checklist against Meta's own
tools.

### 8.1 Admin "Meta Integration" diagnostics page (Filament, in the plugin)

| Panel | What it shows / does |
|---|---|
| **Configuration** | Pixel ID set ✓/✗, CAPI token set ✓/✗, catalog enabled ✓/✗, test event code present. |
| **Token check** | Button → Graph `GET /{pixel_id}?fields=id,name` with the token; shows OK / exact Meta error. |
| **Send test event** | Button → sends a CAPI `Purchase` (or `PageView`) with `test_event_code`; shows `events_received`, `fbtrace_id`, any `messages`. Merchant sees it appear in Events Manager → Test Events. |
| **Delivery log** | Table from `meta_capi_events` (event name, event_id, order, status, HTTP code, Meta error, attempts, sent_at), filters for failures, "retry" action. Pruned after 30 days. |
| **Pixel debug mode** | Toggle `meta_debug_mode` (or `?meta_debug=1` per browser) → storefront logs every Pixel call with its eventID and payload to the console, and mirrors the relay request. |
| **Catalog** | Feed URL with copy button, item count, last generated time, "preview first 20 rows", **validation report**: missing image, empty/too-long title, bad price format, duplicate IDs, missing brand, broken image URL (HEAD check on a sample). |
| **ID consistency check** | Takes the last N web orders and verifies every `content_id` they'd send exists in the current feed → shows match % (should be 100%). |

### 8.2 Automated tests

Backend (PHPUnit/Pest, `Http::fake()`):

- COD order → one Purchase dispatched during placement; online order → none at placement,
  one on `OrderPaymentCompleted`; failed payment → none; POS order → none.
- Payload shape, hashing/normalization (Egyptian phone formats: `01x…`, `+201x…`, `201x…`),
  `event_id` determinism, idempotency (second dispatch doesn't resend), disabled/missing token
  → no HTTP call, Graph error → logged, order unaffected.
- Placement response contains `tracking` only when the plugin is active.
- Relay endpoint: whitelist, throttling, rejects malformed `content_ids`, attaches user data.
- Feed: wrong/missing token → 404, disabled → 404; CSV header matches Meta field names;
  variant rows have `item_group_id`; price/sale_price format; availability mapping; absolute
  URLs; HTML stripped; XML variant is well-formed.
- **ID contract test**: IDs produced for a fixture order's CAPI payload ⊆ IDs in the feed for
  the same fixtures.

Frontend (Playwright, existing e2e setup):

- Stub `window.fbq` + intercept `/api/meta/events`; walk browse → PDP → add (card, PDP, modal)
  → checkout → COD purchase; assert each event fires once, with correct `content_ids` format,
  values, and the **same eventID** in Pixel and relay.
- Online payment path: assert no Purchase before redirect, exactly one on the callback page,
  none on refresh.
- Frontend `metaContentId` vs backend `MetaContentId` fixture comparison (shared JSON fixture).

### 8.3 Acceptance checklist (with Meta's tools, once per environment)

1. **Meta Pixel Helper** (Chrome extension): pixel found, events on each page, no errors.
2. **Events Manager → Test Events** with the test code: every event appears from both
   **Browser** and **Server**, marked **Deduplicated**.
3. **Events Manager → Overview**: Event Match Quality for Purchase ≥ "Good" (target 6+/10).
4. **Events Manager → Diagnostics**: no parameter/dedup warnings after 24 h.
5. **Commerce Manager → Catalog → Data sources**: feed fetch succeeds, 0 errors; review
   warnings.
6. **Events Manager → catalog match rate** for ViewContent/AddToCart/Purchase ≥ 90%.
7. Remove the test event code from settings after sign-off.

### 8.4 AGENTS.md / atlas

New plugin, routes (feed, relay), listeners, migrations and settings change architecture,
routes and data declarations: refresh `docs/engineering-atlas` inventories, then run
`python .agents/skills/engineering-atlas/scripts/test_atlas.py` and
`python .agents/skills/engineering-atlas/scripts/atlas.py check .`.

---

## 9. Delivery order

1. Phase 0 — browser Pixel fix + shared tracking module + `metaContentId` (catalog IDs from day one).
2. Phase 1 — plugin skeleton, settings, CAPI Purchase, response contributor, delivery log.
3. Phase 3 — catalog feed (pulled forward: event IDs are only useful once the catalog exists).
4. Phase 2 — relay endpoint + server-side funnel events.
5. Diagnostics page grows with each phase; acceptance checklist at the end.

## 10. Decisions (2026-11-05)

1. Cookie consent: **not needed for now**. No consent gate.
2. POS orders: **never sent** to Meta.
3. Arabic override feed (§7.4): **in v1**.
4. Brand: **store name** (setting defaults to it).
5. Variant pricing: **follow the website's pricing logic** (`CartPriceCalculator`). Today
   that means:
   - Variant row: `price` = `variant.price` (what the cart charges), no `sale_price`.
     The product discount is not applied to variants, and variants have no discount of
     their own.
   - Product without variants: if `price_after_discount` is set and below `base_price` →
     `price` = base, `sale_price` = after; if set but not below base → `price` = after;
     otherwise `price` = base.
   - The feed must reuse/mirror the cart rule, so the advertised price always equals the
     charged price. If pricing rules change later, the feed follows automatically.
6. Inactive products: **included as `out of stock`**.
7. Combo packages: **not yet** (excluded).
