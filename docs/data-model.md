# Data model

Schema is declared in Laravel migrations. Migration files describe intended schema; the state of a particular database depends on which migrations ran there (check `migrations` table per database).

## Stores

| Store | Kind | Holds | Schema source |
| --- | --- | --- | --- |
| Central DB | MySQL (connection from `DB_CONNECTION`) | Tenants, domains, plugin catalog, per-tenant plugin state, central operator users, queue tables | [database/migrations](../database/migrations) |
| Tenant DB `tenant<id>` | MySQL, one per store (prefix `tenant` from [config/tenancy.php](../config/tenancy.php)) | Everything about one store | [database/migrations/tenant](../database/migrations/tenant) (run when the tenant is created) + `app/Plugins/*/database/migrations/tenant` (run when a plugin is activated) |
| Tenant files | `storage/tenant<id>/app[/public]` | Uploaded images and their WebP variants, PWA `manifest.json`, `plugins/<id>/` folders | [`TenantService`](../app/Services/TenantService.php) |

Twenty-nine tenant migrations exist both in `database/migrations/tenant` and in a plugin folder under the same file name (catalog, cart, locations and settings tables). Laravel records migrations by file name, so whichever runs first wins and the other is skipped. Edit both copies when changing one of them, or remove the host copy.

## Central database

```mermaid
erDiagram
  TENANTS ||--o{ DOMAINS : "served at"
  TENANTS ||--o{ TENANT_PLUGINS : has
  PLUGINS ||--o{ TENANT_PLUGINS : "installed as"
  TENANTS {
    string id PK
    json data "name, theme, admin_* set by central panel"
  }
  DOMAINS {
    int id PK
    string domain UK
    string tenant_id FK
  }
  PLUGINS {
    string id PK "manifest id, e.g. cart-engine"
    string name
    string category
    bool is_core
    json dependencies
  }
  TENANT_PLUGINS {
    int id PK
    string tenant_id FK
    string plugin_id FK
    string status "entitled | active | deactivated"
    json settings
    timestamp activated_at
  }
```

`plugins` is synchronized from `plugin.json` files by `php artisan plugin:discover` ([`PluginRegistry::syncDatabase`](../app/Core/Plugins/Services/PluginRegistry.php)). `tenant_plugins` is written by [`PluginLifecycleService`](../app/Core/Plugins/Services/PluginLifecycleService.php). Central also has `users` (operators for `/central`), `jobs`, `failed_jobs`, `cache`, `personal_access_tokens`, and a `bulk_notifications` table that is a leftover duplicate of the tenant table (see [Push notifications](features/push-notifications.md#troubleshooting)).

The central panel's tenant form stores `admin_name`, `admin_email` and a bcrypt hash of `admin_password` in the tenant's `data` JSON ([CreateTenant page](../app/Filament/Central/Resources/TenantResource/Pages/CreateTenant.php)).

## Tenant database by area

### Customers and settings

| Table | Purpose | Owner |
| --- | --- | --- |
| `users` | Customers and store admins (`is_admin`), Google id, `expo_token` | host, [`User`](../app/Models/User.php) |
| `addresses` | Customer address book linked to an area | [locations-delivery](features/locations-delivery.md) |
| `guest_users` | Guest checkout identities keyed by phone + country code | [guest-checkout](features/guest-checkout.md) |
| `settings` | Key/value store settings (`key`, `value`, `type`) incl. gateway and SMTP credentials | [store-settings](features/store-settings.md) |
| `notifications`, `user_notifications`, `bulk_notifications` | In-app notification inbox, push campaign records | [push-notifications](features/push-notifications.md) |
| `sessions`, `password_reset_tokens`, `cache`, `jobs`, `failed_jobs` | Framework tables inside the tenant DB | host |

### Catalog

```mermaid
erDiagram
  CATEGORIES ||--o{ PRODUCTS : contains
  PRODUCTS ||--o{ PRODUCT_VARIANTS : "has sizes"
  EXTRA_OPTIONS ||--o{ EXTRA_OPTION_ITEMS : offers
  EXTRA_OPTIONS ||--o{ PRODUCTS : "attached to"
  WEIGHT_OPTIONS ||--o{ WEIGHT_OPTION_VALUES : offers
  WEIGHT_OPTIONS ||--o{ PRODUCTS : "attached to"
  SECTIONS ||--o{ SECTION_PRODUCTS : lists
  PRODUCTS ||--o{ SECTION_PRODUCTS : "shown in"
  PACKAGES ||--o{ PACKAGE_GROUPS : has
  PACKAGE_GROUPS ||--o{ PACKAGE_ITEMS : offers
  PRODUCTS ||--o{ PACKAGE_ITEMS : "used in"
  PRODUCTS {
    int id PK
    string name
    string name_ar
    decimal base_price
    decimal price_after_discount
    bool sell_by_weight
    bool is_active
  }
  PACKAGE_GROUPS {
    string selection_type "all | choose_one | choose_multiple"
    int min_selections
    int max_selections
  }
```

Tables: `categories`, `products`, `product_variants` (`price`, `is_available`), `extra_options` (selection rules), `extra_option_items` (`price`), `weight_options`, `weight_option_values` (`value`), `sections`, `section_products` ([catalog](features/catalog.md)); `packages`, `package_groups`, `package_items`, `package_availabilities`, `package_branch` ([combo packages](features/combo-packages.md); the legacy `package_product` pivot is dropped by a 2025-11 migration); `reviews` ([reviews](features/product-reviews.md)); `product_pos_mappings`, `pos_line_mappings` ([POS](features/pos-integration.md)).

### Locations

```mermaid
erDiagram
  GOVERNORATES ||--o{ AREAS : contains
  BRANCHES ||--o{ AREAS : serves
  AREAS ||--o{ ADDRESSES : "located in"
  USERS ||--o{ ADDRESSES : owns
  AREAS {
    decimal shipping_cost "delivery fee"
    int branch_id FK
    bool is_active
  }
  BRANCHES {
    string link "POS base URL"
    text pos_api_key "encrypted, added by pos-integration"
    bool is_active
  }
```

### Cart and orders

```mermaid
erDiagram
  CARTS ||--o{ CART_ITEMS : contains
  CART_ITEMS ||--o{ CART_ITEM_EXTRAS : "with add-ons"
  CART_ITEMS ||--o{ CART_ITEM_COMPONENTS : "combo parts"
  USERS ||--o| CARTS : "owns (user_id)"
  ORDERS ||--o{ ORDER_ITEMS : contains
  ORDER_ITEMS ||--o{ ORDER_ITEM_EXTRAS : "with add-ons"
  ORDER_ITEMS ||--o{ ORDER_ITEM_COMPONENTS : "combo parts"
  ORDERS ||--o{ ORDER_STATUS_EVENTS : "status history"
  ORDERS ||--o| ORDER_POS_DELIVERIES : "POS delivery"
  ORDER_POS_DELIVERIES ||--o{ ORDER_POS_DELIVERY_ATTEMPTS : attempts
  USERS ||--o{ ORDERS : places
  GUEST_USERS ||--o{ ORDERS : places
  COUPONS ||--o{ ORDERS : "applied to"
  CARTS {
    int user_id "signed-in cart"
    string session_id "guest cart"
  }
  CART_ITEMS {
    string line_type "product | package"
    int line_reference_id
    decimal quantity
    int weight_multiplier
  }
  ORDERS {
    string order_number UK "ORD-XXXXXXXX"
    string status
    string payment_status
    string payment_method
    string type "web_delivery | web_takeaway | pos"
    decimal total
    string pos_status
    json delivery_address "snapshot"
  }
```

Orders snapshot customer name/phone/email, the delivery address, product names (EN/AR) and unit prices, so later catalog edits do not change past orders. `payment_events` records each gateway webhook (`gateway`, `transaction_id`) for idempotency; `meta_order_tracking` and `meta_capi_events` support [Meta marketing](features/meta-marketing.md).

### Content

`hero_sliders` ([hero banners](features/hero-banners.md)), `pages` ([CMS pages](features/cms-pages.md)), `sample_greetings` (demo plugin).

## Lifecycles

### Order status

```mermaid
stateDiagram-v2
  [*] --> pending : order placed
  pending --> processing : POS / admin
  pending --> cancelled
  processing --> out_for_delivery
  processing --> cancelled
  processing --> completed : takeaway only
  out_for_delivery --> completed
  completed --> [*]
  cancelled --> [*]
```

Enum values and Arabic labels: [`OrderStatus`](../app/Plugins/OrderManagement/src/Enums/OrderStatus.php) (`processing` is labelled "قيد التحضير", `completed` "مكتمل (تم التوصيل)"). Allowed transitions: [`OrderStatusTransitions`](../app/Plugins/OrderManagement/src/Services/OrderStatusTransitions.php). Every status change fires `OrderStatusUpdated` from the model ([Order.php](../app/Plugins/OrderManagement/src/Models/Order.php#L183)).

### Payment status

```mermaid
stateDiagram-v2
  [*] --> pending
  pending --> processing
  pending --> completed
  pending --> failed
  processing --> completed
  processing --> failed
  failed --> processing : customer retries
  failed --> completed
  completed --> refunded
```

Source: [`PaymentStateMachine`](../app/Plugins/PaymentProcessing/src/Services/PaymentStateMachine.php). Entering `completed` fires `OrderPaymentCompleted`.

### POS delivery status (`orders.pos_status`)

`not_ready → pending/ready → queued → sending → sent`, with `retry_wait`, `needs_verification` and `failed` branches. See [POS integration](features/pos-integration.md#states).

### Plugin state per tenant

`entitled → active ⇄ deactivated`; uninstall deletes the `tenant_plugins` row and calls the plugin's `onUninstall` hook ([`PluginLifecycleService`](../app/Core/Plugins/Services/PluginLifecycleService.php)). Deactivation keeps tables and files.

## Data rules

- Money is stored as decimals in the store currency (setting `currency`, default `EGP`). Gateways receive amounts in cents (`total × 100`) and webhooks must match exactly.
- Times use the application timezone, which is UTC (no `timezone` override in [config/app.php](../config/app.php)).
- Customer accounts are per tenant: the same email can exist in several stores independently.
- Guest identity is the pair (`phone`, `phone_country_code`), default country code `+20`.

## Changing the schema

- Host table used by every tenant: add a migration under `database/migrations/tenant/` and run `php artisan tenants:migrate`.
- Plugin table: add a migration under `app/Plugins/<Name>/database/migrations/tenant/`. New activations run it automatically; for tenants that already have the plugin active run `php artisan tenants:plugin:migrate --plugin=<id>` (optionally `--tenant=<id>`) ([command](../app/Console/Commands/TenantsPluginMigrateCommand.php)).
- Central table: `database/migrations/` and `php artisan migrate`.
- The project's migration stubs omit `down()` methods, so plan forward-only fixes rather than rollbacks ([Deployment](deployment.md#rollback)).
