# Interfaces

All storefront and integration endpoints are served on a **store domain** (tenant identified by host). Central domains serve only `/central`, `/up` and Laravel/Filament internals.

## Overview

| Interface | Consumers | Auth | Definition |
| --- | --- | --- | --- |
| Storefront JSON API (`/api/...`) | React SPA, Expo app | Session cookie (+ CSRF for mutations) where required | [routes/tenant.php](../routes/tenant.php), `app/Plugins/*/routes/tenant.php` |
| Store admin panel `/admin` | Store admins | Filament login, `users.is_admin` | [AdminPanelProvider](../app/Providers/Filament/AdminPanelProvider.php) |
| Central panel `/central` | Platform operators | Filament login (central `users`) | [CentralPanelProvider](../app/Providers/Filament/CentralPanelProvider.php) |
| Payment webhooks | Paymob, Kashier | Gateway HMAC | [PaymentProcessing routes](../app/Plugins/PaymentProcessing/routes/tenant.php) |
| POS status callback | Branch POS | `Authorization: Bearer <integration token>` | [OrderManagement routes](../app/Plugins/OrderManagement/routes/tenant.php) |
| Outbound POS API | Turbo Tenant → branch POS | none sent by client | [`POSApiClient`](../app/Plugins/PosIntegration/src/Services/POS/POSApiClient.php), [CREATE_ORDER_API_DOC.md](../CREATE_ORDER_API_DOC.md) |
| Meta catalog feed | Meta Commerce Manager | Secret token in URL | [MetaMarketing routes](../app/Plugins/MetaMarketing/routes/tenant.php) |
| Artisan CLI | Developers, operators | Shell access | [app/Console/Commands](../app/Console/Commands) |

## Conventions

- **Envelope.** Most endpoints return `{"status":"success","data":...,"message":...}` via [`ApiResponse`](../app/Http/Responses/ApiResponse.php); paginated lists add pagination meta. Errors on `api/*` are `{"status":"error","message":"...","errors":{field:[...]}}` with the HTTP status (422 validation, 401 unauthenticated, 404 not found / feature inactive) ([bootstrap/app.php](../bootstrap/app.php)).
- **Feature gating.** A plugin endpoint returns `404 {"status":"error","message":"Feature not available"}` when the plugin is inactive for the store.
- **Auth.** Same-origin session cookies. Call `GET /sanctum/csrf-cookie` once, then send `X-XSRF-TOKEN` (axios does this automatically). CSRF is skipped only for `api/webhooks/*` and `api/order-status`.
- **Localization.** Many records carry `name`/`name_ar`-style pairs; the client picks the language.
- **Rate limits.** Listed per endpoint below where configured.

## Endpoints by area

### Platform and accounts (host)

| Method | Path | Purpose | Auth / limit | Handler | Feature |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/tenant/bootstrap` | Store, user, settings, active plugins, cart count, prefetched data | public | [BootstrapController](../app/Http/Controllers/Api/BootstrapController.php) | [Store settings](features/store-settings.md) |
| POST | `/api/tenant/auth/login` | Email/password login (optional `expo_token`) | public | [AuthController](../app/Http/Controllers/Api/AuthController.php) | [Accounts](features/customer-accounts.md) |
| POST | `/api/tenant/auth/register` | Create customer account | public | AuthController | Accounts |
| POST | `/api/tenant/auth/logout` | End session | session | AuthController | Accounts |
| GET | `/api/tenant/auth/user` | Current user or null | public | AuthController | Accounts |
| POST | `/api/tenant/auth/google/native` | Sign in with a Google ID token (mobile) | 10/min | [GoogleNativeAuthController](../app/Http/Controllers/Api/GoogleNativeAuthController.php) | Accounts |
| POST | `/api/tenant/auth/forgot-password`, `/reset-password` | Password reset by email | 6/min | AuthController | Accounts |
| PATCH | `/api/tenant/auth/profile` | Update name/email | auth | AuthController | Accounts |
| PUT | `/api/tenant/auth/password` | Change password | auth, 6/min | AuthController | Accounts |
| GET | `/auth/google`, `/auth/google/callback` | Web Google OAuth redirect flow | guest | [GoogleAuthController](../app/Http/Controllers/Auth/GoogleAuthController.php) | Accounts |
| GET | `/manifest.json` | Store's PWA manifest | public | [routes/tenant.php](../routes/tenant.php) | Store settings |
| GET | `/{any}` | SPA shell for client routes | public | `view('app')` | — |

### Catalog and content

| Method | Path | Purpose | Auth | Feature |
| --- | --- | --- | --- | --- |
| GET | `/api/catalog-products` | Paginated active products: `search`, `category_ids[]`, `min_price`, `max_price`, `sort_by` (`name`/`price`/`created_at`), `sort_order`, `per_page` ≤ 50 | public | [Catalog](features/catalog.md) |
| GET | `/api/catalog-products/{id}` | Product with variants, extras, weights, related and promotional products | public | Catalog |
| GET | `/api/catalog-products/sections`, `/categories`, `/suggestions`, `/by-ids` | Home shelves, categories, search suggestions, batch lookup | public | Catalog |
| GET | `/api/packages`, `/api/packages/{id}` | Sellable combo packages | public | [Combo packages](features/combo-packages.md) |
| GET/POST | `/api/products/{product}/reviews` | Approved reviews (10 per page) / submit review (multipart, images ≤ 2 MB) | public | [Reviews](features/product-reviews.md) |
| GET | `/api/hero-banners` (alias `/api/hero-sliders`) | Active banners | public | [Hero banners](features/hero-banners.md) |
| GET | `/api/cms-pages`, `/api/cms-pages/{slug}` | Active pages | public | [CMS pages](features/cms-pages.md) |
| GET/POST | `/api/customer-favorites`, `/api/customer-favorites/sync` | Hydrate product details for favorite ids | public | [Favorites](features/favorites.md) |

### Cart, checkout, orders

| Method | Path | Purpose | Auth | Feature |
| --- | --- | --- | --- | --- |
| GET | `/api/cart` | Lines, subtotals, total | session (user or guest) | [Cart](features/cart.md) |
| POST | `/api/cart/items` | Add product line (or `type`-specific line such as a package) | session | Cart |
| PATCH/DELETE | `/api/cart/items/{itemId}` | Change quantity/notes, remove line | session | Cart |
| DELETE | `/api/cart` | Clear cart | session | Cart |
| POST | `/api/cart/sync` | Re-sync client cart | session | Cart |
| GET | `/api/locations-delivery`, `/governorates`, `/branches` | Delivery geography and branches | public | [Locations](features/locations-delivery.md) |
| GET/POST/PUT/PATCH/DELETE | `/api/locations-delivery/addresses[/{address}]` | Address book | auth + owner policy | Locations |
| POST | `/api/coupons/validate` | Check a code against the current cart: `code`, `address_id?`, `guest_phone?` | session | [Coupons](features/coupons.md) |
| POST | `/api/orders/place` | Place order: `branch_id`, `type` (`web_delivery`/`web_takeaway`), `payment_method`, `address_id?`, `coupon_id?`, `note?`, plus plugin fields (`guest_data`, billing data) | session or guest | [Checkout](features/checkout-and-orders.md) |
| GET | `/api/orders`, `/api/orders/{orderId}` | Customer's order history / detail | auth | Checkout |
| POST | `/api/guest-checkout/track` (alias `/api/tenant/guest-checkout/track`) | Guest tracking by `order_number` + `phone` (+ `phone_country_code`), or `order_number` + `token` | public | [Guest checkout](features/guest-checkout.md) |
| GET | `/api/orders/{orderId}/payment-status` | Stored payment status | order owner | [Payments](features/payments.md) |
| GET | `/api/payments/{order}/kashier-config` | Hosted-checkout parameters for Kashier | order owner | Payments |
| GET | `/orders/{orderId}/payment/callback` | Browser return from gateway; redirects to SPA with `?status=success|failed|pending` | — | Payments |

### Notifications and marketing

| Method | Path | Purpose | Auth / limit | Feature |
| --- | --- | --- | --- | --- |
| GET | `/api/notifications`, `/unread-count` | In-app inbox | auth | [Push notifications](features/push-notifications.md) |
| POST | `/api/notifications/{id}/mark-as-read`, `/mark-all-as-read`, `/clear-read` | Inbox actions | auth | Push notifications |
| DELETE | `/api/notifications/{id}` | Delete notification | auth | Push notifications |
| POST | `/api/meta/events` | Relay browser pixel event to Conversions API (`event_name` in an allow-list, `event_id`, `event_source_url`, `custom_data`) | 120/min | [Meta marketing](features/meta-marketing.md) |
| GET | `/meta/catalog/{token}.{csv|xml}`, `/meta/catalog/{token}.ar.csv` | Product catalog feed / Arabic override feed | token | Meta marketing |

## Inbound integrations

| Name | Path | Payload | Guarantees | Source |
| --- | --- | --- | --- | --- |
| Paymob webhook | `POST /api/webhooks/paymob` | Paymob transaction callback; HMAC in request | Signature check, idempotent per `(gateway, transaction_id)`, amount (cents) and currency must match, state-machine transitions only | [PaymobWebhookController](../app/Plugins/PaymentProcessing/src/Http/Controllers/PaymobWebhookController.php), [PaymentWebhookService](../app/Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php) |
| Kashier webhook | `POST /api/webhooks/kashier` | Kashier event; signature header | Same as above | [KashierWebhookController](../app/Plugins/PaymentProcessing/src/Http/Controllers/KashierWebhookController.php) |
| POS order status | `POST /api/order-status` | `{"orderNumber":"ORD-…","status":"processing"}`; status ∈ `pending, processing, out_for_delivery, completed, cancelled` | Bearer token, 60/min, 409 on disallowed transition, writes `order_status_events` with `source=integration` | [OrderStatusController](../app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderStatusController.php) |

## Outbound integrations

| Service | Used for | Credentials (where configured) | Failure behavior | Feature |
| --- | --- | --- | --- | --- |
| Paymob | Card/wallet payment intention, hosted checkout | Store Settings → payment tab (secret, public, integration ids, HMAC) | Order marked payment `failed`, cart kept, customer sees error | [Payments](features/payments.md) |
| Kashier | Hosted payment | Store Settings → payment tab (merchant id, API key, secret) | Same | Payments |
| Branch POS | `GET /api/can-accept-order`, `GET /api/get-shift-id`, `POST /api/web-orders/place-order` on each branch's `link` | Branch `link` (admin), integration token for callbacks | Retries, deferral, `needs_verification` on uncertain submission | [POS](features/pos-integration.md) |
| POS master product repository | Product import and price sync | Store Settings → POS tab, `products_repo_link` | Admin page shows error | POS |
| Meta Graph API | Conversions API events | Store Settings → Meta tab (pixel id, access token, test code); `META_GRAPH_VERSION`, `META_HTTP_TIMEOUT` | Queued job, results stored in `meta_capi_events` | [Meta marketing](features/meta-marketing.md) |
| Expo push | Push notifications | User `expo_token` | Per-user failure counted in campaign stats | [Push notifications](features/push-notifications.md) |
| Google | OAuth sign-in, ID-token verification | Store Settings → Google tab; `GOOGLE_ALLOWED_CLIENT_IDS` | Login rejected | [Accounts](features/customer-accounts.md) |
| SMTP | Password reset, order emails | Store Settings → SMTP tab, applied per request by [ConfigureTenantMail](../app/Listeners/ConfigureTenantMail.php) | Laravel mail exception | [Store settings](features/store-settings.md) |

## Command-line interface

| Command | Purpose | Mutates data? |
| --- | --- | --- |
| `app:create-tenant --id= --domain=` | Create a store, its DB, domain, storage and manifest (defaults `kofe` / `kofe.localhost`) | Yes: creates a database |
| `plugin:discover` / `plugin:clear` | Sync `plugin.json` files into the central `plugins` table and cache / remove the cache | Central `plugins` |
| `tenant:plugin:activate {tenant} {plugin}` | Activate a plugin (checks dependencies, runs its migrations, calls `onActivate`) | Tenant schema + `tenant_plugins` |
| `tenant:plugin:deactivate {tenant} {plugin}` | Deactivate (refuses core plugins and plugins others depend on) | `tenant_plugins` |
| `tenant:plugin:list {tenant}` | Show plugin states for a store | No |
| `tenants:plugin:migrate [--tenant=] [--plugin=] [--force]` | Run pending plugin migrations for active plugins | Tenant schema |
| `make:plugin` | Scaffold a plugin ([MakePluginCommand](../app/Console/Commands/MakePluginCommand.php)) | Writes files |
| `media:migrate-variants` | Generate WebP variants for existing images and update records | Tenant files + rows |
| `notifications:send-scheduled` | Dispatch due scheduled campaigns (scheduled every minute) | Queues jobs |
| `expo:send-test` | Send a test push to every user with a token | Sends pushes |
| `app:create-test-order` | Create a sample order with variants/extras/weights for POS testing | Tenant data |
| `app:database-fresh` | **Destructive:** `migrate:fresh` on central and drop `tenantresturant`, then create the default tenant | Drops data |
| `serve:fast` | Local server with OPcache | No |
| Stancl: `tenants:migrate`, `tenants:seed`, `tenants:run`, `tenants:list` | Run host tenant migrations/seeders/commands per tenant | Depends |
