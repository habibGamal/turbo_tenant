# Product overview

## What it is

Turbo Tenant is a multi-tenant online ordering platform for restaurants and food brands. Each store (a *tenant*) gets its own domain, its own database, a customer-facing storefront (a React single-page app), and an Arabic-first admin panel. A store owner chooses which capabilities to switch on (catalog, cart, checkout, online payment, coupons, POS integration, push notifications and so on) from a plugin marketplace inside their admin panel. Platform operators create stores from a separate central panel.

## Actors and roles

| Actor | What they do | Where | Permission source |
| --- | --- | --- | --- |
| Customer (signed in) | Browse, order, pay, track orders, save addresses, review products, receive push notifications | Storefront on the store's domain, Expo mobile app | Session auth via [`AuthController`](../app/Http/Controllers/Api/AuthController.php) |
| Guest customer | Order with name + phone only, track with order number + phone | Storefront (when [Guest checkout](features/guest-checkout.md) is active) | [`GuestCheckoutPermissionPipe`](../app/Plugins/GuestCheckout/src/Pipelines/Pipes/GuestCheckoutPermissionPipe.php) |
| Store admin | Manage catalog, orders, delivery areas, promotions, content, settings, plugins | `/admin` on the store's domain | `users.is_admin` in the tenant DB, checked by [`User::canAccessPanel`](../app/Models/User.php#L52) |
| Platform operator | Create stores, assign domains, create the first store admin | `/central` on a central domain | Any user in the central DB ([`User::canAccessPanel`](../app/Models/User.php#L52) only restricts the `admin` panel) |
| Branch POS system | Receives orders, pushes order status back | `{branch link}/api/web-orders/place-order`, `POST /api/order-status` | Bearer integration token ([`VerifyIntegrationToken`](../app/Plugins/OrderManagement/src/Http/Middleware/VerifyIntegrationToken.php)) |
| Payment gateway (Paymob, Kashier) | Confirms payments asynchronously | `POST /api/webhooks/{paymob,kashier}` | HMAC signature per gateway |
| Meta (Facebook) | Receives conversion events, pulls the product catalog feed | Graph API, `/meta/catalog/{token}.{csv,xml}` | Access token / secret feed token |

## Capability map

Each capability is a plugin that a store can activate. See the [feature catalog](features/README.md) for status and details.

- **Platform:** [Store provisioning](features/tenant-provisioning.md), [Plugin marketplace](features/plugin-marketplace.md), [Store settings and branding](features/store-settings.md), [Customer accounts](features/customer-accounts.md)
- **Menu:** [Catalog and products](features/catalog.md), [Combo packages](features/combo-packages.md), [Media optimizer](features/media-optimizer.md)
- **Ordering:** [Cart](features/cart.md), [Checkout and orders](features/checkout-and-orders.md), [Delivery areas and branches](features/locations-delivery.md), [Guest checkout](features/guest-checkout.md), [Online payments](features/payments.md), [Coupons](features/coupons.md), [POS integration](features/pos-integration.md)
- **Engagement:** [Favorites](features/favorites.md), [Product reviews](features/product-reviews.md), [Hero banners](features/hero-banners.md), [CMS pages](features/cms-pages.md), [Push notifications](features/push-notifications.md), [Meta marketing](features/meta-marketing.md)

## Tech stack

- **Backend:** PHP 8.3+, Laravel 12, `stancl/tenancy` (database-per-tenant), Filament 4 (`/admin` and `/central`), Laravel Sanctum 4 (SPA session cookies), Laravel Socialite 5 (Google OAuth), Spatie Image 3 (WebP variants), Expo push notifications, MySQL (production/local) and SQLite (tests).
- **Frontend:** React 18, TypeScript 5, Vite 6, React Router 6, TanStack React Query 5, Tailwind CSS 4, Radix UI / Shadcn UI, Lucide icons, `i18next` / `react-i18next`.
- **Tooling:** Pest 4 (including browser tests), Playwright (live E2E), Larastan / PHPStan level 5, Laravel Pint, Rector.
- **Modular plugins (18 under `app/Plugins/`):** `store-settings` (core), `media-optimizer` (core), `catalog-products`, `cart-engine`, `order-management`, `locations-delivery`, `guest-checkout`, `payment-processing`, `coupons-promotions`, `combo-packages`, `pos-integration`, `push-notifications`, `meta-marketing`, `product-reviews`, `customer-favorites`, `hero-banners`, `cms-pages`, `sample-greeting`.

## Key business rules

- **Isolation:** every store has its own database (`tenant{id}`), its own storage folder, cache tag and queue context ([config/tenancy.php](../config/tenancy.php)). Customer accounts are per store.
- **Feature availability:** a plugin's API routes return `404 {"message":"Feature not available"}` unless the plugin is active for the store ([`EnsurePluginActive`](../app/Core/Plugins/Http/Middleware/EnsurePluginActive.php)). Only `store-settings` and `media-optimizer` are activated automatically for a new store.
- **Prices are computed on the server** from the cart at order time: line subtotals, then coupon discount, tax, cash-on-delivery fee and delivery fee ([checkout](features/checkout-and-orders.md#how-it-works)).
- **Order status** moves `pending → processing → out_for_delivery → completed` (takeaway orders may go `processing → completed`), or to `cancelled` from the first two states ([`OrderStatusTransitions`](../app/Plugins/OrderManagement/src/Services/OrderStatusTransitions.php)).
- **Online payments are confirmed only by the gateway webhook.** The browser redirect only reads the stored status ([payments](features/payments.md)).
- **Bilingual:** catalog, locations, banners, pages and settings carry English and Arabic fields; the admin panel is in Arabic and the storefront switches between English/LTR and Arabic/RTL.

## Glossary

| Term | Meaning | In code |
| --- | --- | --- |
| Tenant / store | One customer business with its own domain(s) and database | [`App\Models\Tenant`](../app/Models/Tenant.php), `tenants`, `domains` tables |
| Central | Host-level context (central domains, central DB) where tenants and plugin catalog live | `tenancy.central_domains` in [config/tenancy.php](../config/tenancy.php) |
| Plugin | A self-contained feature module under `app/Plugins/<Name>` with a `plugin.json` manifest | [`PluginManifest`](../app/Core/Plugins/DTOs/PluginManifest.php) |
| Core plugin | Plugin with `"is_core": true`, auto-activated and not removable | [`ActivateCorePlugins`](../app/Core/Plugins/Jobs/ActivateCorePlugins.php) |
| Contribution | A class a plugin tags into an extension point (e.g. bootstrap payload, settings tab, order rule) | [`PluginContributions`](../app/Core/Plugins/Services/PluginContributions.php) |
| Pipe | A step in the order validation or pricing pipeline | [`OrderPipelineRegistry`](../app/Plugins/OrderManagement/src/Pipelines/OrderPipelineRegistry.php) |
| Section | A curated product shelf on the storefront home page | [`Section`](../app/Plugins/CatalogProducts/src/Models/Section.php) |
| Extra option | A group of add-ons (e.g. sauces) attached to a product, with min/max selection rules | [`ExtraOption`](../app/Plugins/CatalogProducts/src/Models/ExtraOption.php) |
| Weight option | Selectable weights for products sold by weight | [`WeightOption`](../app/Plugins/CatalogProducts/src/Models/WeightOption.php) |
| Package / combo | A bundle of products with choose-one/choose-many groups at a bundle price | [`Package`](../app/Plugins/ComboPackages/src/Models/Package.php) |
| Branch | A physical kitchen; its `link` is the base URL of its POS | [`Branch`](../app/Plugins/LocationsDelivery/src/Models/Branch.php) |
| Governorate / area | Delivery geography; each area has a delivery fee and a serving branch | [`Area`](../app/Plugins/LocationsDelivery/src/Models/Area.php) |
| Integration token | Bearer secret the POS uses to update order status | [`IntegrationTokenService`](../app/Plugins/OrderManagement/src/Services/IntegrationTokenService.php) |

## Scope boundaries

- Payment card data never touches the app: customers pay on Paymob's or Kashier's hosted page/iframe.
- Kitchen operations happen in the branch POS; Turbo Tenant only sends the order and receives status updates.
- There is no inventory/stock tracking; availability is the product's `is_active` flag and variant `is_available`.
- The Expo mobile app that consumes the same API is not in this repository.
