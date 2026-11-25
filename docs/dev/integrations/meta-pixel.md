# Meta Pixel, CAPI, and Catalog

Stores connect to Meta advertising using a vendor-neutral storefront event bus (`core/analytics`), the `meta-marketing` frontend plugin for browser Pixel tracking, a server-side Conversions API (CAPI) relay and purchase job, and a live CSV/XML product catalog feed.

**Plugin:** `meta-marketing` · **Code:** [`app/Plugins/MetaMarketing`](../../../app/Plugins/MetaMarketing), [`resources/js/plugins/meta-marketing`](../../../resources/js/plugins/meta-marketing), [`resources/js/core/analytics`](../../../resources/js/core/analytics/index.ts) · **Related:** [Meta marketing](../features/meta-marketing.md)

## Architecture

1. Storefront themes and hooks emit neutral events through `emitCommerceEvent(name, payload)` in [core/analytics](../../../resources/js/core/analytics/index.ts).
2. [`meta-pixel-tracker.ts`](../../../resources/js/plugins/meta-marketing/services/meta-pixel-tracker.ts) subscribes to the bus, buffers up to 50 events until bootstrap settings sync, defers `ReactPixel.init` to browser idle time, passes hashed/normalized advanced matching (`em`, `external_id` as `u:<id>`) for signed-in users, fires `ReactPixel.fbq('track', name, data, { eventID })`, and mirrors funnel events to `POST /api/meta/events` using `fetch(..., { keepalive: true })` with XSRF retry on `419`.
3. `_fbp` and `_fbc` cookies are excluded from Laravel cookie encryption in [bootstrap/app.php](../../../bootstrap/app.php) so [`CaptureOrderTrackingContext`](../../../app/Plugins/MetaMarketing/src/Listeners/CaptureOrderTrackingContext.php) can store them at order placement.

## Content ID scheme

Pixel events, CAPI payloads, and the catalog feed share one ID format:

| Item | `content_ids` / feed `id` | `content_type` / `item_group_id` | Source |
| --- | --- | --- | --- |
| Product without variants | `"{productId}"` | `"product"` | [`MetaContentId`](../../../app/Plugins/MetaMarketing/src/Support/MetaContentId.php), [`metaContentId`](../../../resources/js/plugins/meta-marketing/utils/content-id.ts) |
| Product with selected variant | `"{productId}-{variantId}"` | `"product"` | same |
| `ViewContent` on product with variants (none selected) | `["{productId}"]` | `"product_group"` | `ThemedProductShow` |

## Tracked events

| Event | Trigger | Deduplication `eventID` | Server CAPI |
| --- | --- | --- | --- |
| `PageView` | Route change via `MetaPixelRouteTracker` (`router.tracking` slot) | UUID | Relayed via `POST /api/meta/events` |
| `ViewContent` | Product page load and variant selection | UUID | Relayed |
| `Search` | Menu search query commit (`?search=`) | UUID | Relayed |
| `AddToWishlist` | Favorite toggle add | UUID | Relayed |
| `AddToCart` | `useAddToCart` success (skipped on undo/restore; weight items converted via [`cartItemToCommerceItem`](../../../resources/js/plugins/cart-engine/utils/cart-item-converter.ts)) | UUID | Relayed |
| `InitiateCheckout` | Checkout mount with non-empty cart | UUID | Relayed |
| `AddPaymentInfo` | Checkout submission with non-COD method | UUID | Relayed |
| `CompleteRegistration` | Account registration success | UUID | Relayed |
| `Purchase` | `usePlaceOrder` (COD) or `usePaymentStatus` once paid (online, stashed in `sessionStorage` `meta:purchase:{order_id}`) | `purchase.{order_id}` from [`PurchaseTrackingResponseContributor`](../../../app/Plugins/MetaMarketing/src/Http/Responders/PurchaseTrackingResponseContributor.php) | Queued by [`SendPurchaseToMeta`](../../../app/Plugins/MetaMarketing/src/Listeners/SendPurchaseToMeta.php) on `OrderFinalized` / `OrderPaymentCompleted` (POS orders skipped) |

`POST /api/meta/events` ([`RelayEventRequest`](../../../app/Plugins/MetaMarketing/src/Http/Requests/RelayEventRequest.php)) allows only the 8 funnel events above (never `Purchase`), validates `content_ids` and `contents` shapes, and is throttled at `120/min`.

## Catalog feed

[`CatalogFeedController`](../../../app/Plugins/MetaMarketing/src/Http/Controllers/CatalogFeedController.php) and [`CatalogFeedService`](../../../app/Plugins/MetaMarketing/src/Catalog/CatalogFeedService.php) generate feeds per request (no Laravel cache, avoiding cache-tag failures on `database` cache drivers):

- Primary feed: `GET /meta/catalog/{token}.csv` and `GET /meta/catalog/{token}.xml`
- Arabic override feed: `GET /meta/catalog/{token}.ar.csv` (`override = ar_AR`, Arabic product/variant titles)
- Products without an image are skipped; inactive products/variants emit `out of stock`; weight-sold items carry `custom_label_0 = by_weight`; pricing mirrors `CartPriceCalculator` (variant prices ignore `price_after_discount`).

## Diagnostics and debug mode

- **Storefront debug:** enable `meta_debug_mode` in settings or append `?meta_debug=1` (persisted in `sessionStorage`) to log `[MetaPixel]` and `[MetaRelay]` payloads to the browser console.
- **Admin diagnostics:** **المحتوى والتسويق › تشخيص تكامل ميتا** ([`MetaDiagnostics`](../../../app/Plugins/MetaMarketing/src/Filament/Pages/MetaDiagnostics.php)) verifies the access token against Meta Graph API (`META_GRAPH_VERSION`, default `v26.0`), sends test events, lists `meta_capi_events` with retry for failed purchases, previews and validates catalog feed rows, and checks ID consistency against recent web orders.
