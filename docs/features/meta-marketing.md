# Meta marketing

## Overview

Connects a store to Meta (Facebook/Instagram) advertising: the browser Pixel, server-side Conversions API (CAPI) events including purchases, and a product catalog feed for Commerce Manager. Browser and server events share event ids so Meta deduplicates them.

**Status:** live · **Plugin:** `meta-marketing` ([plugin.json](../../app/Plugins/MetaMarketing/plugin.json)) · **Depends on:** order-management, catalog-products, store-settings · **Related:** [Checkout](checkout-and-orders.md), [Payments](payments.md), [Catalog](catalog.md). Design plan: [META_PIXEL_AND_CAPI_PLAN.md](../META_PIXEL_AND_CAPI_PLAN.md)

## How to use

### Store admin: connect Meta

Store settings › Meta tab:

1. **معرف البكسل (Pixel / Dataset ID)**: the Pixel id. **وضع التصحيح للبكسل** enables pixel debug mode.
2. **إرسال الأحداث من الخادم إلى ميتا** (send server events) + **رمز الوصول (Access Token)** from Events Manager; optional **رمز أحداث الاختبار (Test Event Code)** while testing.
3. Catalog: **تفعيل رابط تصدير الكتالوج** (enable catalog feed), **مفتاح رابط الكتالوج** (feed key; **توليد مفتاح جديد** generates one), **العلامة التجارية في الكتالوج** (brand). In Commerce Manager add a scheduled data feed with `https://<store domain>/meta/catalog/<key>.csv` (or `.xml`), plus `/meta/catalog/<key>.ar.csv` as the Arabic language override.
4. Check **المحتوى والتسويق › تشخيص تكامل ميتا** (Meta diagnostics): configuration status, recent CAPI results, catalog validation issues and id consistency between pixel events and the feed.

### Customer

Nothing to do. Browsing, add to cart, checkout steps and registration fire Pixel events; purchases are sent from the server after the order is confirmed.

## How it works

1. **Pixel bootstrap:** [`MetaMarketingBootstrapContributor`](../../app/Plugins/MetaMarketing/src/Bootstrap/MetaMarketingBootstrapContributor.php) gives the SPA the pixel id and debug flag; the frontend plugin ([resources/js/plugins/meta-marketing](../../resources/js/plugins/meta-marketing), [core/analytics](../../resources/js/core/analytics/index.ts)) fires browser events. `_fbp`/`_fbc` cookies are excluded from cookie encryption ([bootstrap/app.php](../../bootstrap/app.php)) so the server can read them.
2. **Relay:** the browser also posts each event to `POST /api/meta/events` (120/min). [`RelayEventRequest`](../../app/Plugins/MetaMarketing/src/Http/Requests/RelayEventRequest.php) allows only `PageView`, `ViewContent`, `Search`, `AddToWishlist`, `AddToCart`, `InitiateCheckout`, `AddPaymentInfo`, `CompleteRegistration`; [`RelayEventController`](../../app/Plugins/MetaMarketing/src/Http/Controllers/RelayEventController.php) queues [`SendMetaRelayEvent`](../../app/Plugins/MetaMarketing/src/Jobs/SendMetaRelayEvent.php) with the same `event_id`.
3. **Purchase:** [`CaptureOrderTrackingContext`](../../app/Plugins/MetaMarketing/src/Listeners/CaptureOrderTrackingContext.php) (on `OrderCreated`) saves `_fbp`, `_fbc`, IP and user agent in `meta_order_tracking`. [`SendPurchaseToMeta`](../../app/Plugins/MetaMarketing/src/Listeners/SendPurchaseToMeta.php) (on `OrderFinalized` for cash orders and `OrderPaymentCompleted` for paid orders; POS-origin orders skipped) queues [`SendMetaPurchaseEvent`](../../app/Plugins/MetaMarketing/src/Jobs/SendMetaPurchaseEvent.php) (5 tries). [`PurchaseTrackingResponseContributor`](../../app/Plugins/MetaMarketing/src/Http/Responders/PurchaseTrackingResponseContributor.php) gives the browser the matching event id for its own Purchase pixel.
4. **CAPI client:** [`MetaConversionsClient`](../../app/Plugins/MetaMarketing/src/Capi/MetaConversionsClient.php) posts to `https://graph.facebook.com/{META_GRAPH_VERSION}/{pixel}/events` with hashed user data from [`UserDataBuilder`](../../app/Plugins/MetaMarketing/src/Capi/UserDataBuilder.php); results stored in `meta_capi_events`. Server events are sent only when CAPI is enabled and both pixel id and token are set.
5. **Catalog feed:** [`CatalogFeedController`](../../app/Plugins/MetaMarketing/src/Http/Controllers/CatalogFeedController.php) returns 404 unless the feed is enabled and the token matches (`hash_equals`); [`CatalogFeedService`](../../app/Plugins/MetaMarketing/src/Catalog/CatalogFeedService.php) builds items (ids via [`MetaContentId`](../../app/Plugins/MetaMarketing/src/Support/MetaContentId.php), prices consistent with storefront prices) and writes CSV/XML.

## Configuration

| Setting | Effect | Default |
| --- | --- | --- |
| `facebook_app_id` | Pixel / dataset id | — |
| `meta_debug_mode` | Pixel debug | off |
| `meta_capi_enabled`, `meta_capi_access_token`, `meta_capi_test_event_code` | Server events | off |
| `meta_catalog_enabled`, `meta_catalog_feed_token`, `meta_catalog_brand` | Catalog feed | off |
| `META_GRAPH_VERSION`, `META_HTTP_TIMEOUT` (env) | Graph API version, timeout | `v26.0`, 10 s |

Requires a queue worker.

## Testing

- Automated: [tests/Feature/MetaMarketing](../../tests/Feature/MetaMarketing) (CAPI client, purchase, relay, cookies, user data normalization, catalog content/endpoint/freshness/validator/pricing parity, diagnostics page, settings).
- Browser: [meta-marketing.live.spec.ts](../../tests/e2e/plugins/meta-marketing.live.spec.ts) (Meta network calls are blocked in the live fixture).
- Manual: set a Test Event Code, place a COD order, confirm the Purchase in Events Manager › Test events.

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| No server events | CAPI off, token or pixel missing, worker down | Diagnostics page; worker |
| Purchases counted twice | Browser and server event ids differ | Check diagnostics id consistency |
| Feed URL 404 | Feed disabled or wrong key | Enable feed; regenerate key and update Commerce Manager |
| Relay returns 422 | Event name not in the allow-list or bad content ids | Use allowed events |

## History

- Unreleased (working tree): Meta marketing plugin: CAPI purchase and relay events, catalog feed, diagnostics.
- 2026-10: Facebook Pixel purchase tracking in checkout.
