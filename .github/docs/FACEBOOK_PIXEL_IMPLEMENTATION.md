# Meta (Facebook) Pixel & Conversions API Tracking Architecture

## 1. Executive Summary

This document describes the modern, decoupled Meta (Facebook) Pixel and Conversions API (CAPI) tracking architecture implemented in the Turbo Tenant React 18 SPA storefront (`resources/js`).

Previous implementations scattered direct `react-facebook-pixel` calls across theme components (`ThemedProductShow.tsx`, `ProductOptionsModal.tsx`, `CartSummary.tsx`, `ThemedFavoriteButton.tsx`, `pages/Checkout.tsx`), omitted initialization (`InitPixel.tsx` was unmounted), lacked deduplication `eventID`s, and missed quick-add tracking entirely.

The new architecture adopts a **vendor-neutral event bus** in core and a standalone **`meta-marketing` frontend plugin**, keeping storefront themes thin and decoupled.

---

## 2. High-Level Architecture

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Turbo Tenant Storefront                         │
│                                                                        │
│  Themes / Pages / Hooks / Plugins                                      │
│  (PDP, Menu, Cart, Quick-Add, Favorites, Checkout, Auth)               │
│                                │                                       │
│                                ▼                                       │
│                emitCommerceEvent(eventName, payload)                   │
│                                │                                       │
│                                ▼                                       │
│        ┌───────────────────────────────────────────────┐               │
│        │   Vendor-Neutral Commerce Event Bus           │               │
│        │   (resources/js/core/analytics/index.ts)      │               │
│        └───────────────────────────────────────────────┘               │
│                                │                                       │
│                                ▼ onCommerceEvent(...)                  │
│        ┌───────────────────────────────────────────────┐               │
│        │   Meta Marketing Frontend Plugin              │               │
│        │   (resources/js/plugins/meta-marketing/)      │               │
│        └───────┬───────────────────────────────┬───────┘               │
└────────────────┼───────────────────────────────┼───────────────────────┘
                 │                               │
                 ▼ (ReactPixel.fbq)              ▼ (fetch POST /api/meta/events)
     ┌───────────────────────┐       ┌───────────────────────┐
     │   Meta Browser Pixel  │       │  CAPI Server Relay    │
     │   (fbevents.js)       │       │  (Conversions API)    │
     └───────────────────────┘       └───────────────────────┘
```

### 2.1 Component Layering & Separation of Concerns

1. **Vendor-Neutral Commerce Event Bus (`resources/js/core/analytics/`)**:
   - Exposes `emitCommerceEvent` and `onCommerceEvent`.
   - Core has zero knowledge of Facebook or Meta specifics; core never imports any plugin.
   - Standard event names: `PageView`, `ViewContent`, `Search`, `AddToWishlist`, `AddToCart`, `InitiateCheckout`, `AddPaymentInfo`, `CompleteRegistration`, `Purchase`.
   - Neutral payload formats with generic items: `{ product_id, variant_id?, quantity, item_price, name?, category? }`, `value`, `currency`, and opaque `tracking` DTO.

2. **Meta Marketing Plugin (`resources/js/plugins/meta-marketing/`)**:
   - Registered in `resources/js/plugins/registry.ts` at application bootstrap (`registerMetaMarketingPlugin()`).
   - Listens to the bus, manages Pixel initialization once (`settings.meta_pixel_id`), hashes/formats advanced matching data, generates deduplication event IDs, formats parameters according to Meta's catalog specifications, and mirrors funnel events to the backend relay (`POST /api/meta/events`).

3. **Route Tracker Slot (`router.tracking`)**:
   - Rendered inside `<BrowserRouter>` in `resources/js/core/router/AppRouter.tsx` via `<PluginSlot name="router.tracking" />`.
   - Observes `location.pathname + location.search` changes and fires `PageView` exactly once on initial load (no double PageView) and on every client-side navigation.

---

## 3. Content ID Scheme (Catalog & Feed Alignment)

To guarantee dynamic product ads and catalog matching succeed with high match rates, event `content_ids` match the Meta Product Catalog feed IDs:

| Catalog Item Type | Meta Content ID Format | `content_type` |
|---|---|---|
| Product **without** variants | `"{productId}"` | `"product"` |
| Product **with** selected variant | `"{productId}-{variantId}"` | `"product"` |
| ViewContent on product with variants (none selected yet) | `["{productId}"]` | `"product_group"` |

- Implemented on the frontend in `metaContentId(productId, variantId?)` (`resources/js/plugins/meta-marketing/utils/content-id.ts`).
- Matches the backend contract in `App\Plugins\MetaMarketing\src\Support\MetaContentId::for($productId, $variantId)`.
- All IDs are cast to strings in event payloads.

---

## 4. Tracked Events Specification

| Event | Source / Location | Trigger Condition | Key Parameters | Server Relay (`POST /api/meta/events`) |
|---|---|---|---|:---:|
| **PageView** | `MetaPixelRouteTracker.tsx` (via `router.tracking` slot) | Every `pathname + search` change; exactly once on first load | `event_source_url` | Yes |
| **ViewContent** | `ThemedProductShow.tsx` | PDP mount (once per `product.id`) and whenever a new variant is selected | `content_ids`, `content_type` (`"product"` or `"product_group"`), `contents`, `value` (effective price), `currency`, `content_name` | Yes |
| **Search** | `ThemedMenuPage.tsx` | Committed query in URL (`?search=`), debounced ~800ms, once per distinct query | `search_string` | Yes |
| **AddToWishlist** | `useFavoritesStorage.ts` | When product heart is toggled into favorites (card + PDP) | `content_ids`, `contents`, `content_type: "product"`, `value`, `currency` | Yes |
| **AddToCart** | `useAddToCart.ts` (`onSuccess`) | All cart additions (PDP, modal, card quick-add, menu row quick-add). Suppressed when `track: false` (e.g. undo/restore) | `content_ids`, `contents` (`[{ id, quantity, item_price }]`), `content_type: "product"`, `value` (`qty * unit_price_incl_extras`), `currency` (resolved from `settings.currency`) | Yes |
| **InitiateCheckout** | `Checkout.tsx` (page mount) | When checkout mounts with a non-empty cart (once per cart signature) | `content_ids`, `contents`, `content_type: "product"`, `value` (subtotal), `currency`, `num_items` (total units) | Yes |
| **AddPaymentInfo** | `Checkout.tsx` (`handlePlaceOrder`) | When order is submitted with an online payment method (`payment_method !== 'cod'`) | `value`, `currency` | Yes |
| **CompleteRegistration**| `AuthContext.tsx` (`registerMutation.onSuccess`) | Successful user registration | `status: true` | Yes |
| **Purchase** | `usePlaceOrder.ts` (COD) / `usePaymentStatus.ts` (Online) | At order placement for COD (`requires_payment=false`) via `usePlaceOrder`; inside `usePaymentStatus` once polling confirms paid | `tracking.*` server-verified values (`purchase_event_id`, `order_id`, `value`, `currency`, `content_ids`, `contents`, `num_items`) | **No** (Server sends CAPI Purchase directly) |

---

## 5. Pricing, Line Calculation & Cart Item Conversion

To ensure strict consistency between `AddToCart` and `InitiateCheckout`, line conversions to `CommerceItem` are centralized in a single helper in the `cart-engine` plugin:
`resources/js/plugins/cart-engine/utils/cart-item-converter.ts` (`cartItemToCommerceItem`).

### 5.1 Standard Products
- `item_price`: Unit price including extras (`Number(item.price) + Number(item.extras_total)`).
- `quantity`: Number of units (`Number(item.quantity) || 1`).

### 5.2 Sell-By-Weight Products
- In the storefront cart, line pricing represents `weight_multiplier * weight_option_value`.
- To prevent catalog ad discrepancies where the catalog item expects line unit price, the converter computes:
  - `quantity`: Multiplier steps added (`weight_multiplier`, default 1).
  - `item_price`: Charged unit amount (`subtotal / weight_multiplier`).
  - Total line value: `quantity * item_price` = total charged line amount (`subtotal`).

### 5.3 Currency Fallback in Meta Marketing Tracker
- When an event emits `value` without an explicit `currency` (such as `AddToCart` or `AddToWishlist`), the `meta-marketing` tracker automatically falls back to `settings.currency` resolved from tenant bootstrap.
- Emitters may still specify `currency` explicitly (e.g. `InitiateCheckout` or `Purchase`), which takes precedence.

---

## 6. Deduplication, Buffering & Conversions API Relay

### 6.1 Funnel Events Deduplication
1. For every standard funnel event, `generateEventId()` produces a unique UUID (`crypto.randomUUID()` with fallback).
2. The browser Pixel tracks the event with:
   ```ts
   ReactPixel.fbq('track', name, customData, { eventID });
   ```
3. If `settings.meta_server_events_enabled` is active, the frontend sends a `POST /api/meta/events` request with the **exact same `event_id`**:
   ```json
   {
     "event_name": "AddToCart",
     "event_id": "8f8b89d4-1a32-475a-bc82-d2780f295b92",
     "event_source_url": "https://tenant.example/menu",
     "custom_data": {
       "content_ids": ["12"],
       "contents": [{ "id": "12", "quantity": 1, "item_price": 50 }],
       "content_type": "product",
       "value": 50,
       "currency": "EGP"
     }
   }
   ```
4. Request uses `ensureCsrfToken()` with `X-XSRF-TOKEN` header and `fetch(..., { keepalive: true })` so navigations never drop in-flight events.
5. Meta's Conversions API uses `(event_name, event_id)` to deduplicate browser and server hits into a single conversion.

### 6.2 Purchase Deduplication & Hook-Level Tracking
Purchase tracking is fully decoupled from theme and page components:
- **COD (Cash on Delivery / No Payment Gateway Required)**:
  - In `usePlaceOrder.ts` (`onSuccess`), when `data.tracking` is present and `!data.requires_payment`, `emitCommerceEvent('Purchase', { tracking: data.tracking })` fires immediately with `tracking.purchase_event_id`.
- **Online Payments (Card / Mobile Wallet)**:
  - In `usePlaceOrder.ts` (`onSuccess`), when `data.requires_payment` is true, the tracking payload is saved to `sessionStorage` under `meta:purchase:{order_id}`.
  - In `usePaymentStatus.ts`, polling checks order status. As soon as status becomes paid (`is_paid` or `order.payment_status === 'completed'`), it reads and removes the key from `sessionStorage`, setting a `hasEmittedPurchaseRef` sentinel.
  - This guarantees:
    1. Zero theme dependency (works identically across default and custom themes).
    2. Protection against React Query polling refetches.
    3. Protection against page refresh (the key is destroyed immediately on first execution).
- The server sends the CAPI Purchase directly from queued backend listeners (`OrderFinalized` / `OrderPaymentCompleted`), ensuring 100% server-side revenue accuracy.

### 6.3 Event Buffering Before Bootstrap Sync
To avoid losing events emitted before bootstrap data resolves (e.g. initial `PageView` before `MetaPixelRouteTracker`'s effect runs):
- `handleCommerceEvent` buffers up to 50 events in memory while awaiting the first `updateTrackerConfig()` call.
- Upon the first sync:
  - If a valid `pixelId` is present, all buffered events are sequentially flushed through the normal tracking and relay pipeline.
  - If no `pixelId` is configured, the buffer is emptied and discarded cleanly.
- Subsequent events bypass the buffer and execute immediately.

---

## 7. Advanced Matching

On initialization, if an authenticated user session is active (`AuthContext`), customer parameters are passed to `ReactPixel.init`:
- `em`: Customer email address (trimmed and lowercased).
- `external_id`: Deterministic customer identifier in format `"u:{user.id}"` (matches server-side external ID format).
- Note: Users in this system do not store phone numbers in the primary users table; phone parameters are captured during checkout by server-side CAPI.

---

## 8. Debug Mode

Debug mode outputs detailed console diagnostics for all Pixel calls and CAPI relays:
- Enabled when `settings.meta_debug_mode === true` OR URL query string contains `?meta_debug=1`.
- `meta_debug=1` is automatically remembered in `sessionStorage` for the duration of the browser session.
- Diagnostic logs:
  - `console.info('[MetaPixel]', { name, eventID, payload })`
  - `console.info('[MetaRelay]', { name, eventID, body })`

---

## 9. Verification & Acceptance Testing

### 9.1 Meta Pixel Helper (Chrome Extension)
1. Open the storefront in Chrome with Meta Pixel Helper installed.
2. Browse pages, add products to cart, and observe that:
   - Pixel ID matches `settings.meta_pixel_id`.
   - `PageView` fires once per page navigation.
   - `AddToCart` fires with string `content_ids` (e.g. `["14"]` or `["14-2"]`).
   - `eventID` parameter is present on every event.

### 9.2 Events Manager -> Test Events
1. In Meta Events Manager, navigate to **Data Sources** -> Select Dataset / Pixel -> **Test Events**.
2. Enter the tenant URL with `?meta_debug=1`.
3. Complete an end-to-end checkout.
4. Verify in the Test Events activity table:
   - Browser events show green with source **Browser**.
   - Server relay events show source **Server**.
   - Events sharing the same `event_id` are grouped and marked as **Deduplicated**.
   - Purchase shows correct server total and currency.

### 9.3 Automated E2E Test Suite
The Playwright test spec is located at:
`tests/e2e/plugins/meta-marketing.live.spec.ts`

To execute:
```bash
npx playwright test tests/e2e/plugins/meta-marketing.live.spec.ts --project=theme-default --workers=1 --retries=0 --reporter=list
```
