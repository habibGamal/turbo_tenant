# Plugins Backend — Code Quality Review

Date: 2026-10-29 · Branch: `refactoring` · Scope: `app/Core/Plugins/**`, `app/Plugins/**` (405 PHP files, ~22.5k LOC), plus core files that reach into plugins.

Lenses: SOLID, clean code, unnecessary code, if-branching, plugin boundaries.

Severity: **P0** = bug / broken today · **P1** = design flaw that causes bugs or blocks plugin independence · **P2** = maintainability / cleanup.

---

## 0. Scorecard

| Area | Verdict |
|---|---|
| Microkernel core (`app/Core/Plugins`) | Good shape. Small, well-typed, mostly SRP. A few redundancies (see §5). |
| Plugin boundaries | **Weakest area.** 3 dependency cycles, 8 undeclared deps, core hard-codes plugin classes, plugins import core shims of other plugins. |
| SOLID | Pipelines + `resolveRelationUsing` + `PaymentGatewayInterface` show the right direction. Undermined by concrete deps, fat interfaces, closed factories, and a god `Order` table. |
| Branching | Concentrated in 5 files: `CouponService` (51 ifs), `ProductValidator` (28), `POSProductResolver` (24), `ProductPOSImporterService` (19), `POSCustomerDataFormatter` (16). |
| Unnecessary code | 12 empty plugin classes, 8 unused Config DTOs, dead relation registrations, dead guards, 2 shim files that don't parse. |
| API layer | No API Resources (0), 3 FormRequests total, 2 response envelope styles (`status` ×52 vs `success` ×23). |

---

## 1. P0 — Bugs found during the review

| # | Finding | Where |
|---|---|---|
| 1.1 | **Two committed files don't parse** (`unexpected variable "$n"`). A bad sed replacement left `Requests$n` in the `use` line and alias. Anything that autoloads `App\Http\Requests\StoreCartItemRequest` fatals; static analysis / classmap builds choke. Nothing references them → delete both. | `app/Http/Requests/StoreCartItemRequest.php:5`, `app/Http/Requests/UpdateCartItemRequest.php:5` |
| 1.2 | **Admin "Tax rate" setting is ignored.** The Settings page saves `tax_rate`, but `CalculateTaxPipe` reads `OrderManagementConfig::getTaxRate()` → `config('app.tax_rate')`. Same for service rate. Store owners change tax in the UI and nothing happens. | `OrderManagement/src/DTOs/OrderManagementConfig.php:16-24`, `StoreSettings/src/Filament/Pages/Settings.php:234` |
| 1.3 | **"Minimum order amount" setting is never enforced.** Saved in UI, loaded into `StoreConfig::$minOrderAmount`, read by nobody. | `StoreSettings/src/DTOs/StoreConfig.php:15` |
| 1.4 | **Fake 4.5★ rating.** Product details API returns `rating = 4.5` when there are no reviews (or reviews plugin is off); the sibling `ProductController` returns `0.0` for the same case. | `CatalogProducts/.../CatalogProductsApiController.php:146`, `CatalogProducts/.../ProductController.php:36` |
| 1.5 | **`OrderCreated` fires inside the DB transaction, before items exist.** It's dispatched from `Order::created` (model hook) while `OrderRepository` is still inside `DB::transaction` and hasn't inserted items. Sync listeners (admin DB notification, coupon usage increment, push job dispatch) run even if the transaction later rolls back. `OrderCreated::$validationContext` is always `null` because the model hook can't pass it. | `OrderManagement/src/Models/Order.php:130-133`, `OrderManagement/src/Services/Repositories/OrderRepository.php:38-99` |
| 1.6 | **Coupon usage counted for orders that never get paid.** `RecordCouponUsageListener` increments `usage_count`/`total_consumed` on create; when online payment fails `markOrderAsPaymentFailed` cancels the order but never gives the usage back. | `CouponsPromotions/src/Listeners/RecordCouponUsageListener.php`, `OrderRepository.php:113-121` |
| 1.7 | **First-order coupons can be reused.** "First order only" and "max per user" count orders with status `processing/completed/out_for_delivery` only — `pending` orders are ignored, so a customer can place several pending COD orders with a first-order coupon. | `CouponsPromotions/src/Services/CouponService.php:329-384` |
| 1.8 | **Dead relation registrations** — model methods always win over `resolveRelationUsing`, so these plugin registrations never take effect: `User::orders/addresses` (hard-coded in core `User`), `Branch::orders` (hard-coded in `Branch`), `User::notifications/unreadNotifications` (collide with Laravel's `Notifiable::notifications()`). PushNotifications actually relies on core `User::userNotifications()` instead of its own registration. | `app/Models/User.php:90-108`, `LocationsDelivery/src/Models/Branch.php:26-33`, `PushNotifications/PushNotificationsServiceProvider.php:37-42` |
| 1.9 | **Coupon time window breaks across midnight.** `valid_hours` uses string compare `now < start \|\| now > end`; `22:00 → 02:00` never matches. Same pattern in store opening hours. | `CouponService.php:411-415`, `OrderManagementConfig.php:329-332` |
| 1.10 | **Category-scoped coupons can never apply at checkout.** `ValidateCouponPipe` passes `CartService::getCart()['items']` (built by `CartItemFormatter`) to `CouponService`, which checks `$item['product']['category_id']` — a key the formatter never emits. The coupon *preview* endpoint passes because it trusts client-sent `cart_items`; the unit test passes because it hand-builds arrays with `category_id`. See §9.3. | `CartEngine/.../CartItemFormatter.php:32-51`, `CouponService.php:239,269`, `CouponsPromotions/.../ValidateCouponPipe.php:39,51` |
| 1.11 | **Quick-add modal drops required extras.** `ProductCard` and `ThemedMenuPage` fetch `/api/catalog-products/{id}` and pass the raw `data` to `ProductOptionsModal`, which reads `product.extraOption`. The API sends `extra_option` (only the full product page renames it). The extras section never renders, `min_selections` reads as 0, and the backend then rejects add-to-cart with "Minimum N extra selection(s) required". The camelCase keys came from the unrouted `ProductController`. See §9.3. | `resources/js/themes/default/plugins/catalog-products/components/ProductCard.tsx:80`, `ThemedMenuPage.tsx:305`, `components/ProductOptions.tsx:147`, `ThemedProductShow.tsx:77` |
| 1.12 | **`?per_page=-1` dumps the whole catalog.** `min((int) per_page, 50)` has no lower bound; Laravel's `limit()` ignores negatives, so `paginate(-1)` runs with no `LIMIT`. Same for `suggestions?limit=-1`. | `CatalogProductsApiController.php:26,302` |
| 1.13 | **Customer order API leaks internal columns.** `Order` has no `$hidden`; `OrderController::index/show` return the raw model, including `payment_data` (gateway payload), `transaction_id`, `merchant_order_id`, `paymob_order_id`, `shift_id`, `pos_failure_reason`. | `OrderController.php:55-63,80-102`, `Order.php` |
| 1.14 | **Guest delivery orders have no address snapshot.** `delivery_address` is filled only from `address_id` (registered users). Guest orders depend on the mutable `GuestUser` row, which the next order from the same phone overwrites, so old orders show the new address. | `OrderRepository.php:41-54`, `GuestCheckout/.../GuestUserService.php:21-37` |
| 1.15 | **Orders accepted for inactive branches.** `branch_id` validates `exists:branches,id` only; no pipe checks `is_active`. | `OrderController.php:141` |

---

## 2. Plugin boundary violations

### 2.1 Actual import graph vs. declared `dependencies`

```
OrderManagement  → CouponsPromotions   UNDECLARED  (cycle: Coupons depends on OrderManagement)
OrderManagement  → GuestCheckout       UNDECLARED  (cycle: GuestCheckout depends on OrderManagement)
LocationsDelivery→ OrderManagement     UNDECLARED  (cycle: OrderManagement depends on LocationsDelivery)
CatalogProducts  → PosIntegration      HIDDEN via core shim App\Filament\Forms\Components\PosItemSelect
                                       (cycle: PosIntegration depends on CatalogProducts)
ComboPackages, CouponsPromotions, CustomerFavorites, HeroBanners,
ProductReviews, PushNotifications → StoreSettings   UNDECLARED (StoreSettings is core, so harmless, but the manifest lies)
```

Cycle sites:
- `OrderManagement/src/Services/Repositories/OrderRepository.php:61-68` — reads `Coupon::find()` directly. Coupons already registers `Order::coupon` and passes `applied_coupon` through the validation context; the repository should only read context.
- `OrderManagement/src/Filament/Resources/Orders/Tables/OrdersTable.php:59` — queries `GuestUser` directly. Use the `guestUser` relation GuestCheckout registers, or a column contributed by the plugin.
- `LocationsDelivery/src/Models/Branch.php:26-33` — `orders()` with a string class name + `class_exists` + a fallback to `hasMany(Model::class)` (abstract class — would crash). OrderManagement already registers this relation; delete the method.
- `CatalogProducts/src/Filament/Resources/Products/Schemas/ProductForm.php:140-170` and `ExtraOptions/Schemas/ExtraOptionForm.php:94-122` — a whole "POS mappings" tab lives in Catalog. Catalog shouldn't know POS exists. Needs a form-extension point (e.g. `ProductFormExtender` tag that PosIntegration contributes a `Tab` to). `ExtraOptionItem::$fillable` contains `pos_mapping_type` for the same reason.

**Fix:** add a CI check (the script used for this review) that fails when a plugin imports a plugin not in its manifest `dependencies`, and when the dependency graph has cycles.

### 2.2 Core → plugin coupling (core should never import plugins)

| File | Problem |
|---|---|
| `app/Models/User.php:90-108` | Hard-codes `addresses`, `orders`, `userNotifications`, `unreadUserNotifications` to plugin models. Plugins already register these via `resolveRelationUsing` — remove from core (rename PushNotifications' relation to avoid `Notifiable` collision). |
| `app/Enums/SettingKey.php:31-69` | Core enum lists Paymob, Kashier, POS repo link, Google OAuth, mail, order-status keys. Every new plugin setting edits core (OCP). Each plugin should own its keys (e.g. `PaymentProcessing\src\Settings\PaymentSettingKey`). Today keys are also mixed: enum (`SettingKey::PAYMOB_*`) vs. raw strings (`'pos-integration.timeout_seconds'`, `'coupons-promotions.enabled'`). |
| `app/Enums/PaymentMethod.php` → `posMapping()` | POS translation table inside a core enum. Belongs in PosIntegration (`OrderPOSPayloadBuilder`). |
| `app/Enums/OrderStatus.php`, `OrderType.php`, `PaymentStatus.php` | Order-domain enums live in core while `Order` lives in a plugin. Move into OrderManagement (keep core aliases during migration). |
| `app/Filament/Widgets/StoreSetupChecklist.php:45-165` | Core widget references 10 plugin classes behind `isPluginActive && class_exists` ladders. Invert it: a `SetupChecklistItem` contract that each plugin contributes (same idea as `BootstrapContributor`). |
| `app/Console/Commands/CreateTestOrder.php` | Core command imports 16 classes from 4 plugins. Move to OrderManagement (or a dev-only plugin). |
| `app/Listeners/ConfigureTenantMail.php`, `app/Services/Auth/GoogleOAuthConfigService.php`, `app/Providers/Filament/AdminPanelProvider.php` | Core depends on `StoreSettings\SettingService`. Since store-settings is the settings kernel, extract `SettingsRepository` contract into `app/Core` and let StoreSettings implement it. |
| `app/Mail/OrderUpdateMail.php`, `app/Filament/Resources/Users/Tables/UsersTable.php:25` | Order-specific code in core. |

### 2.3 Plugins importing *core shims* of other plugins

Legacy alias files (`app/DTOs/Order/*`, `app/Exceptions/OrderValidationException.php`, `app/Http/Requests/OrderStatusUpdateRequest.php`, `app/Filament/Forms/Components/PosItemSelect.php`, `app/Filament/Resources/HeroSliderResource.php`) re-export plugin classes from core namespaces. Plugins then import the **shim** instead of the source:

- 5 pipes in OrderManagement + `ValidateCouponPipe` throw `App\Exceptions\OrderValidationException` (a core subclass of OrderManagement's own exception).
- CatalogProducts imports `App\Filament\Forms\Components\PosItemSelect` → hides the Catalog→POS cycle from any plugin-boundary check.

**Fix:** point imports at the plugin namespace, then delete the shims (only `tests/Feature/HeroBannersTest.php` still uses one).

### 2.4 Runtime "is another plugin on?" branching inside plugins

`isPluginActive()` calls in plugin domain code are a smell: the caller is reaching across a boundary and branching on it.

| Site | Better |
|---|---|
| `OrderRepository.php:43` — `isPluginActive('locations-delivery')` | It's a **declared hard dependency**; the check is always true. Delete. |
| `OrderRepository.php:61` — coupon lookup | Read `applied_coupon` from `OrderValidationContext` (Coupons pipe already puts it there). Snapshot code/ID via a pricing adjustment, not via `Coupon::find`. |
| `OrderController.php:189` — guest check | Duplicates `GuestCheckoutPermissionPipe` + `ValidateUserAuthenticatedPipe`. Resolve guest through a `GuestCustomerResolver` contract (null object when plugin off) and let the pipeline decide. |
| `Order.php:56-120` — `guestCheckoutActive()` in 4 accessors | `customer_name/phone` are already snapshotted on the order; guest fallbacks can be dropped or provided via the `guestUser` relation only when loaded. |
| `CouponService.php:198-201, 340, 372` | Same guest-phone query built twice behind the same `if`. Use a `CustomerOrderHistory` query object; GuestCheckout can decorate it. |
| `CatalogProductsApiController.php:146` + `ProductController.php:35` | Identical review-stats block copy-pasted. ProductReviews should contribute rating data (e.g. `withAvg('reviews','rating')` scope, or a `ProductDetailsContributor`). |
| `CartEngine/src/Listeners/SyncGuestCartOnLogin.php:22-29` | Checks `tenancy()->initialized`, `tenant()`, and `isPluginActive('cart-engine')` — `ActivationGatedListener` already did all three before calling it. |

### 2.5 Shared-table ownership

`Order` `$fillable` holds columns owned by four other plugins: `paymob_order_id`, `merchant_order_id`, `transaction_id`, `payment_data` (PaymentProcessing), `shift_id`, `pos_status`, `pos_failure_reason` (POS), `coupon_id`, `coupon_code` (Coupons), `guest_user_id` (GuestCheckout). `OrderPosStatus` enum lives in OrderManagement. Uninstalling POS can't drop its columns. Longer-term: plugin-owned side tables (`order_pos_states`, `order_payments`) keyed by `order_id`. Short-term: at least move `OrderPosStatus` to PosIntegration and drop the gateway-specific `paymob_order_id`.

`PosIntegration/src/Services/ProductPOSImporterService.php:321-380` writes `Category`/`Product` rows directly. Catalog should expose a `CatalogImporter` / `ProductWriter` contract so validation, media optimization and events stay inside Catalog.

---

## 3. SOLID violations

### S — Single Responsibility

| Class | Responsibilities mixed |
|---|---|
| `OrderRepository` (`OrderManagement/src/Services/Repositories/OrderRepository.php`) | Persistence **+** address snapshot building **+** coupon lookup **+** extras price math **+** clears the cart (`$this->cartService->clearCart`). A repository shouldn't clear carts. Move cart-clearing to `PlaceOrderService` (after commit), address snapshot to a `DeliveryAddressSnapshotter`. |
| `OrderController::placeOrder` (`OrderController.php:138-284`) | 40-line inline validation, guest creation, DTO mapping, 3-way response building. → `PlaceOrderRequest` (FormRequest) + `PlaceOrderData::fromRequest()` + `OrderPlacementResponder`. |
| `ProductPOSImporterService` (461 LOC) | HTTP client for 5 endpoints **+** diffing (new products / price changes) **+** writing Catalog models. Split: `PosMasterClient`, `PosCatalogDiff`, `PosProductImporter`. |
| `OrderManagementConfig` | Named a DTO but it service-locates settings and contains store-hours business logic (`isStoreOpen`). → `StoreHours` value object in StoreSettings. |
| `Settings` Filament page (597 LOC) | Hosts the settings of **every** plugin (payments, POS, OAuth, mail) and hand-codes (de)serialization per key in `mount()` / `save()`. See §3 O. |
| `CouponService` (420 LOC) | Validation rules **+** discount calc **+** shipping calc **+** usage counting **+** order-history queries. |

### O — Open/Closed

- **`PaymentGatewayFactory::getGateway`** (`PaymentProcessing/src/Services/PaymentGatewayFactory.php:40-47`) — hard-coded `match` + `getAvailableGateways()` list; both gateways constructed eagerly on every resolve. Adding a gateway edits the factory, the Settings form `->visible(... === 'paymob')`, and `SettingKey`. → tag gateways (`$app->tag([...], 'payment.gateways')`), key by `getGatewayId()`, resolve lazily.
- **`CouponService::validateCoupon`** — 9 sequential `if (...) return ['valid'=>false,...]` blocks. Each new rule = edit the method. → `CouponRule` interface (`check(CouponContext): ?string`) + tagged rules; service iterates. Kills ~35 ifs.
- **Coupon type / applicability** — `if ($coupon->type === 'percentage') … else …` and `if ($applicableType === 'products') … elseif ('categories')` duplicated in two methods. → `DiscountStrategy` per type, `ApplicabilityScope` per type (enum with methods is enough).
- **Settings page** — other plugins can't add tabs. → `SettingsSectionContributor` contract; each plugin contributes its own `Tab` + key schema; page loops contributors. Fixes §2.2 `SettingKey` too.
- **Core `StoreSetupChecklist`** — see §2.2.
- **Catalog `ProductForm`** — no extension point; POS edits Catalog's form directly. See §2.1.

### L — Liskov

- `OrderPOSPayloadBuilder.php:37,50` calls `method_exists($this->productResolver, 'setMappings')` on a `POSProductResolverInterface`. The interface doesn't declare it, so substitutes behave differently. Either add `withMappings(Collection): self` to the interface (returning a new instance — no mutable state on a singleton) or pass mappings as an argument to `resolveOrderItem()`.
- `CartService::getCart(User|GuestCustomer|null)` — a `GuestCustomer` is treated exactly like `null` (falls through to session id), unlike `resolveCart()` which honours it. Two code paths for "who owns the cart". Use `contextResolver` everywhere.

### I — Interface Segregation

- **`PaymentGatewayInterface`** (`OrderManagement/src/Contracts/PaymentGatewayInterface.php`) has 8 methods (HMAC ×2, webhook parsing, refunds, currency units). OrderManagement uses **2** (`getGatewayId`, `createPaymentIntention`). The rest are PaymentProcessing internals, but the contract is owned by OrderManagement — so OrderManagement's public API changes whenever a gateway detail changes. Split: `PaymentInitiator` (OrderManagement-owned, 1–2 methods) and `GatewayWebhookVerifier` / `RefundingGateway` (PaymentProcessing-owned).
- `TenantPluginInterface` forces 4 lifecycle hooks; 12/17 plugins implement none (§4). Fine for the abstract base, but the per-plugin files are noise.

### D — Dependency Inversion

| Site | Depends on concrete | Use |
|---|---|---|
| `PlaceOrderService.php:24`, `OrderRepository.php:25`, `OrderController.php:35`, `SyncGuestCartOnLogin.php:16` | `CartService` | `CartServiceInterface` (already exists and is bound) |
| `OrderController.php:204` | `app('guest.user.service')` string service-locator | constructor-inject a `GuestCustomerResolver` contract (core) with a null implementation when the plugin is off |
| `CouponService.php:17-21`, `ActivationGatedListener.php:18,39`, `OrderManagementConfig.php:13` | optional ctor param + `app()` fallback | plain required constructor injection (container always provides it) |
| `Settings.php:55,544`, `OrderManagementPlugin.php:23`, `CouponsPromotionsPlugin.php:18` | `app(...)` in methods | inject (Filament pages can use `boot()` injection) |

`app()`/`resolve()` service-locator calls per plugin: OrderManagement 15, PosIntegration 10, CatalogProducts 8, StoreSettings 5, CouponsPromotions 4.

---

## 4. Unnecessary code

| What | Where | Action |
|---|---|---|
| 12 plugin classes whose 4 methods are all empty overrides of the (already no-op) base | `CartEngine`, `CatalogProducts`, `CmsPages`, `ComboPackages`, `CustomerFavorites`, `HeroBanners`, `LocationsDelivery`, `MediaOptimizer`, `PosIntegration`, `ProductReviews`, `PushNotifications`, `StoreSettings` `*Plugin.php` | Delete. `PluginManager::resolvePluginInstance` already returns `null` when the class is absent. Also delete the empty `onActivate/onDeactivate/onUninstall {}` in the 5 that do have `bootForTenant`. Update `MakePluginCommand` stub. |
| 8 Config DTOs bound in providers but read by nobody | `CartEngineConfig`, `CmsPagesConfig`, `ComboPackagesConfig`, `CouponsPromotionsConfig`, `GuestCheckoutConfig`, `ProductReviewsConfig`, `PushNotificationsConfig`, `StoreConfig` | Delete (and their `tenant_scoped` entries) or wire them in. Their `enabled` flags suggest a feature that was never implemented — two "on/off" switches (plugin activation + `enabled` setting) is one too many. |
| `app()->bound(OrderPipelineRegistry::class)` guards | `CouponsPromotionsPlugin.php:17`, `GuestCheckoutPlugin.php:16`, `PaymentProcessingPlugin.php` | Always true — `PluginServiceProvider` registers **every** plugin provider at app boot. Delete the `if`. |
| Provider registration loop in `bootTenant` | `app/Core/Plugins/Services/PluginManager.php:58-62` | No-op: providers were already registered globally in `PluginServiceProvider::register` (`app/Providers/PluginServiceProvider.php:49-55`). Pick one place. |
| `class_exists(OrderPosStatus::class) ? … : 'string'` | `Order.php:155` | Same plugin; always true. |
| Unrouted controllers | `CatalogProducts/.../ProductController.php` (121 LOC), `ProductSearchController.php` (51), `SearchController.php` (35) | No route or test references them. Delete. |
| No-op endpoints | `CustomerFavoritesApiController::sync` / `store` | Validate input and echo it back; nothing is persisted. Delete or implement. |
| `if (! $user) return 401` | `OrderController.php:45, 73` | Routes already wrapped in `auth` middleware. |
| `empty($baseUrl)` checks after `getMasterRepoUrl()` (which already throws when empty) | `ProductPOSImporterService.php` ×5 | Delete. |
| Identical `if/else` branches (`relationLoaded('extras')` true/false run the same loop) | `PosIntegration/src/Services/POS/POSProductResolver.php:68-80` | Collapse to one loop. |
| `addValidationPipe` + `registerValidationPipe` (and pricing pair) — two names for one method | `OrderManagement/src/Pipelines/OrderPipelineRegistry.php:24-54` | Keep one. Also the two lists/getters are copy-paste → one `PipeList` class used twice. |
| `function_exists('tenant')` / `function_exists('tenancy')` | `BelongsToPlugin.php:34,52`, `PluginManager.php:106,130,156`, `EnsurePluginActive.php:20`, `SyncGuestCartOnLogin.php` | stancl/tenancy is a hard dependency; the helpers always exist. |
| Duplicated `canAccess()` / `shouldRegisterNavigation()` bodies | `app/Core/Plugins/Concerns/BelongsToPlugin.php:32-63` | Extract `private static function pluginIsInactive(): bool`. |
| `ensureProductIsActive()` then `validateOptions()` re-loads and re-checks `is_active` | `CartService.php:246-247`, `ProductValidator.php:13-42` | One query. |
| Duplicated weight-option validation | `ProductValidator.php:65-80` vs `146-177` | Extract `assertWeightOption()`. |
| Duplicate ownership check (`resolveCart` → `findCartItemOrFail` → compare `cart_id` → `abort 404`) | `CartService.php:271-279` and `310-318` | `findOwnedItemOrFail($user, $itemId)` on the repository. |
| Billing defaults (`'Cairo'`, `'+201000000000'`, `'customer@example.com'`, `'NA'`) defined twice, and guest/user branches near-identical | `OrderPaymentDispatcher.php:231-266`, `PaymobService.php:443-458` | One `BillingData` value object with a `withDefaults()` — and stop sending fake phone/email to the gateway. |
| Legacy key-format migration on every page load (`open_at`→`from`, `subtitle`→`description`, …) and mirror conversion on save | `Settings.php:58-100`, `565-585` | Run a one-off data migration, then delete both halves. |

---

## 5. Excessive branching — worst offenders and the refactor shape

| File | ifs | Max nesting | Refactor |
|---|---|---|---|
| `CouponsPromotions/src/Services/CouponService.php` | 51 | 4 | Rule objects (§3 O); `DiscountType`/`ApplicableTo` enums with behaviour; return a `CouponValidationResult` VO instead of `['valid'=>…,'message'=>…]` arrays; one `CustomerCouponHistory` query used by both "first order" and "per-user limit". |
| `CartEngine/src/Services/Validators/ProductValidator.php` | 28 | 5 (`validateOptions` is 115 lines) | Split into `VariantRule`, `WeightRule`, `ExtrasRule` each returning `?array $error`; normalize the `extras` input shape (`int` or `{id,quantity}`) once in the FormRequest, not in the validator. |
| `PosIntegration/src/Services/POS/POSProductResolver.php` | 24 | 4 | Remove dead branch (§4); `orWhereIn` flag dance at `:101-112` → build ID sets then a single `where(fn)` with two `whereIn`s. |
| `PosIntegration/src/Services/ProductPOSImporterService.php` | 19 | 3 | 5 copy-pasted fetchers → one `fetch(string $path, array $query = [], int $timeout = 5): array`. Wrap each product import in `DB::transaction` (currently category/product/mapping can be half-written). Throw a typed `PosMasterUnavailable` exception instead of `new Exception('<Arabic text>')` ×12. |
| `PosIntegration/src/Services/POS/POSCustomerDataFormatter.php` | 16 | — | Null-object customer source (user / guest / snapshot) instead of nested fallbacks. |
| `OrderManagement/src/Http/Controllers/Api/OrderController.php` | 12 | 3 | FormRequest + responder (§3 S). |
| `PaymentProcessing/src/Services/PaymobService.php` → `determinePaymentStatus` | — | — | Returns raw strings `'completed'/'processing'/'failed'`; return `PaymentStatus` enum. Same for `KashierService.php:184-186`. |

Magic strings to replace with enums: `'pending'`/`'cancelled'` in `OrderRepository.php:83,117` (the same file uses `OrderStatus::PENDING` 20 lines later), the 4× `['processing','completed','out_for_delivery']` in `CouponService`, `'paymob'/'kashier'` in factory + settings form, `in:card,wallet,cod,kiosk,bank_transfer` / `in:web_delivery,web_takeaway,pos` in `OrderController` (use `Rule::enum(PaymentMethod::class)`).

---

## 6. Clean-code / consistency

- **No API Resources anywhere.** Controllers return raw Eloquent models (`OrderController::index/show` paginate `Order` with all `$fillable` + hidden internals like `payment_data`, `pos_failure_reason`) or hand-built arrays (`CatalogProductsApiController::formatProductSummary`). Introduce `JsonResource`s per plugin — also the natural place for plugins to contribute fields.
- **Two response envelopes:** `{status: 'success'|'error'}` (52 sites) vs `{success: true|false, errors}` (23 sites), sometimes in the same controller (`OrderController`). Pick one; add a tiny `ApiResponse` helper in core.
- **Validation in controllers:** 8 `Validator::make` / `$request->validate` in controllers vs. 3 FormRequests.
- **HTTP concerns in services:** `CartService` calls `abort(404)` ×4 — throw a domain `CartItemNotFound` and map it in the exception handler.
- **Error message leakage:** `PlaceOrderService.php:74` returns `'Failed to place order: '.$e->getMessage()` to the client (DB / container errors reach the storefront). Log the detail, return a generic message.
- **Array-shaped contracts:** `createPaymentIntention(): array{success, data?, checkout_url?, error?}`, `handleWebhook(): array`, `validateCoupon(): array`, `importProducts(): array{imported, failed, errors}`. Replace with small readonly result classes; the array shape is only enforced by docblocks.
- **Gateway-specific leakage in "generic" code:** `PaymentWebhookService.php:24` unwraps `$webhookData['obj']` (Paymob's payload shape) before calling a gateway-agnostic `validateHmac`. Move that into `PaymobService`.
- **`SettingService` has no caching:** every `get()` is one query; `getAllAsArray()` = 51 queries; `PaymentProcessingConfig` alone runs 13 per request it's built. Load all settings once per tenant request (`pluck('value','key')`) and memoize; bust on `set()`.
- **Bool settings stored as `'true'/'false'` strings** with hand-written `filter_var`/ternary conversions in `mount()` and `save()` for each key. Use a typed key schema (`SettingKey::cast()`) once SettingKey moves per plugin.
- **`PluginRegistry::all()`** rescans the filesystem + JSON-decodes 17 manifests on every boot (no cache). `PluginDiscoverCommand` exists — have it write a cached manifest file (like `bootstrap/cache/packages.php`) and load that.
- **`PluginManager::isPluginActive`** hits the DB each call until `bootTenant` has run; Filament calls it per resource/page for `canAccess` + `shouldRegisterNavigation`. Memoize per tenant even when not booted.
- **`PluginLifecycleService::activate`** runs migrations, creates storage, writes the record, then calls `onActivate` — no transaction or compensation; a failing `onActivate` leaves the plugin marked active. The reverse-dependency check in `deactivate` (`:107-117`) belongs in `PluginDependencyResolver::getDependents()` next to `getMissingDependencies()`.
- **`PluginManager::resolvePluginInstance`** derives the class from the plugin id by convention (`foo-bar` → `App\Plugins\FooBar\FooBarPlugin`). The manifest already lists `providers` and `filament` explicitly — add `"plugin": "..."` too and drop the naming convention.
- **`$booted[$manifest->id] = $pluginInstance ?? true`** stores `TenantPluginInterface|true` in one array — store a set of ids and instances separately.
- **Mutable state on a singleton:** `POSProductResolver::setMappings()` (registered as singleton) relies on `try/finally` in the caller to reset. Safe today, fragile in queue workers. Make it immutable (`withMappings()` returns a clone) or pass mappings explicitly.
- **Route boilerplate:** all 13 route files repeat `['web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class, 'plugin.active:<id>']`. A core `PluginRoutes::tenant('id', fn)` macro removes the duplication and makes forgetting the gate impossible.
- **Hard-coded Arabic UI strings in services/exceptions** (`'لا يمكن الاتصال بالنقطة الرئيسية'` ×12, `'ملاحظات'`, `'الوزن'` in POS payload). Use `__()` keys.
- **`GuestUserService::findOrCreate`** overwrites an existing guest's name/address for anyone who knows their phone number. (Security-adjacent; listed here because the fix is a design one: don't mutate the stored profile from an unauthenticated request — snapshot onto the order instead.)

---

## 7. What's already good (keep doing this)

- Manifest-driven kernel with topological boot order and cycle detection (`PluginDependencyResolver`).
- `ActivationGatedListener` — listeners registered once, gated per tenant at dispatch time.
- `resolveRelationUsing` from the *dependent* plugin (Coupons → `Order::coupon`, GuestCheckout → `Order::guestUser`, ProductReviews → `Product::reviews`). This is the right direction — finish it by removing the hard-coded copies (§1.8).
- Order validation/pricing pipelines with priority-ordered pipes that other plugins contribute (`CouponsPromotionsPlugin`, `GuestCheckoutPlugin`, `PaymentProcessingPlugin`). This is the model to copy for settings tabs, product-form tabs, product-details data and setup-checklist items.
- `PaymentGatewayInterface` owned by the consumer (OrderManagement) and implemented by the provider plugin — correct DIP direction, just too wide (§3 I).
- `BootstrapContributor` contract for the storefront bootstrap payload.
- `PaymentStateMachine` with an explicit transition table; webhook idempotency via `PaymentEvent`.

---

## 8. Suggested order of work

1. **P0 fixes** (small, independent): delete the 2 broken shims; wire `tax_rate`/`service_rate`/`min_order_amount` to settings; remove the fake 4.5 rating; move `OrderCreated` dispatch to after commit (`DB::afterCommit` or dispatch from `PlaceOrderService`); release coupon usage on payment failure; count `pending` in coupon history; delete dead relation methods in `User`/`Branch`.
2. **Boundary guard in CI**: script that fails on undeclared plugin imports, cycles, and core → plugin imports (allow-list the current ones, shrink it over time).
3. **Break the 4 cycles** (§2.1): OrderRepository coupon lookup → context; OrdersTable guest → relation; delete `Branch::orders`; POS form tab via a Catalog form-extension contract.
4. **Delete unnecessary code** (§4) — low-risk, shrinks the surface before refactors.
5. **Contribution contracts** to replace core→plugin and plugin→optional-plugin knowledge (§10.2): core `Customer` contract, `CustomerResolver`, `PlaceOrderRulesContributor`, `OrderResourceExtender`, `ProductFormExtender`, `SettingsSectionContributor` (+ per-plugin setting keys), `SetupChecklistItem`, `ProductDetailsContributor`, and `OrderPaymentCompleted` / `OrderFinalized` events instead of cross-plugin column writes.
5b. **Publish provider interfaces** (§10.3): `SettingsRepository` (core), `OrderPipelineRegistrar` or manifest-declared pipes, Catalog `ProductImporter`, OrderManagement `CustomerOrderHistory` / `OrderPaymentRecorder`, LocationsDelivery `DeliveryQuote`; switch the 5 `CartService` injections to `CartServiceInterface`.
6. **Branch-heavy services**: `CouponService` rules, `ProductValidator` rules, `ProductPOSImporterService` split, `PaymentGatewayFactory` → tagged gateways.
7. **API layer**: FormRequests + JsonResources + one envelope.

---

## 9. Deep dives

### 9.1 `OrderController` (`app/Plugins/OrderManagement/src/Http/Controllers/Api/OrderController.php`, 285 LOC)

**Responsibilities today:** order history (index/show), a checkout "bootstrap" that aggregates three plugins, request validation, guest identity resolution, DTO mapping, 3 response shapes, and frontend route building. It should be a thin HTTP adapter.

| # | Line | Finding | Fix |
|---|---|---|---|
| a | 140-176 | 37-line inline `Validator::make`. Enum values copied as strings (`in:card,wallet,cod,kiosk,bank_transfer`, `in:web_delivery,web_takeaway,pos`) and will drift from `PaymentMethod` / `OrderType`. | `PlaceOrderRequest` FormRequest with `Rule::enum(...)`. |
| b | 148 | The storefront can submit `type=pos`. `CalculateDeliveryFeePipe` skips the delivery fee for `pos` and `web_takeaway`, and the POS payload maps the type. | Accept only `web_delivery` / `web_takeaway` from the storefront; `pos` only from the integration path. |
| c | 143, 148 | `type=web_delivery` with no `address_id` (registered user) passes → a delivery order with no address and a 0 delivery fee. | `required_if:type,web_delivery` (registered) / require `guest_data.area_id` (guest), or a `ValidateDeliveryAddressPipe`. |
| d | 141 | Branch existence only (P0 1.15). | `Rule::exists('branches','id')->where('is_active', true)` or a pipe. |
| e | 187-205 | Guest handling: `isPluginActive('guest-checkout')` duplicates `GuestCheckoutPermissionPipe` + `ValidateUserAuthenticatedPipe`, then `app('guest.user.service')` string locator (undeclared dependency on GuestCheckout). `findOrCreate` writes to the DB **before** the validation pipeline runs, so a rejected order still creates/overwrites the guest profile. | Inject a core `GuestCustomerResolver` contract (null object when the plugin is off); don't persist guest data until validation passes. |
| f | 232-234 | Session write (`guest_order_ids`) in an API controller. | GuestCheckout listener, or return a signed tracking token. |
| g | 237-238 | `if ($user && ! ($user instanceof User))` — `$user` is always set here; a type check stands in for "is guest" (`$order->isGuestOrder()` exists). | `$order->isGuestOrder()`. |
| h | 244 | Builds `/orders/track?order_number=…&phone=…`: frontend routes hard-coded in the backend, and the customer's **phone number in a URL** (browser history, proxy logs, referrer). | Return `{order_number, tracking_token}`; the frontend builds the route. |
| i | 236-272 | Branches on `PaymentMethod::COD`, while the service decided offline vs. online with `requiresOnlinePayment()` — two sources of truth. `paymentData` from the result is dropped. | Branch on `$result->requiresPayment`; one `OrderPlacementResponder`. |
| j | 207, 273 | `try { … } catch (Exception)` — `PlaceOrderService` already catches `Throwable` and returns a result; `PaymentMethod::from` is pre-validated. Dead catch. | Remove. |
| k | 45-50, 73-78 | `if (! $user) return 401` under `auth` middleware — dead. | Remove. |
| l | 55-63, 80-102 | Raw `Order` models serialized (P0 1.13). `index` eager-loads `items.product.weightOption` for a list view. | `OrderResource` / `OrderSummaryResource`; load only what the list shows. |
| m | 108-133 | `checkoutData` duplicates `LocationsDeliveryApiController::index` (same addresses + governorates + branches queries) plus the cart. OrderManagement serving LocationsDelivery data = boundary leak, two endpoints to keep in sync. | Frontend composes `/api/cart` + `/api/locations`, or a checkout-bootstrap contributor contract. |
| n | 35 | Depends on concrete `CartService`. | `CartServiceInterface`. |
| o | — | `index/show/checkoutData` use `{status, data}`; `placeOrder` uses `{success, errors}`. | One envelope. |

Target shape:

```php
public function placeOrder(PlaceOrderRequest $request, GuestCustomerResolver $guests): JsonResponse
{
    $result = $this->placeOrderService->placeOrder(
        PlaceOrderData::fromRequest($request, $guests->resolve($request))
    );

    return $this->responder->respond($result); // OrderPlacementResponder
}
```

### 9.2 `CatalogProductsApiController` (`app/Plugins/CatalogProducts/src/Http/Controllers/Api/CatalogProductsApiController.php`, 388 LOC)

| # | Line | Finding | Fix |
|---|---|---|---|
| a | 26, 302 | Negative `per_page` / `limit` → unbounded queries (P0 1.12). | FormRequest: `integer|min:1|max:50`. |
| b | 24-90 | Filter/search/sort parsed by hand: 6 `if`s, `$request->get()` with no validation. | `ProductIndexRequest` + a `ProductFilters` query object (or `Product::scopeFilter()`). |
| c | 44-50, 314-318 | Search clause copy-pasted between `index` and `suggestions`; user input unescaped in `LIKE` (`%` / `_` act as wildcards); leading `%` = full table scan. | `Product::scopeSearch($term)` with escaped wildcards. |
| d | 53-58 | One parameter matched against `name`, `name_ar` **and** `id` (`?category=3` matches id 3 and any category named "3"). | Separate `category_ids[]` (int) from name filtering. |
| e | 85-88 | Sorting by `base_price` ignores discounts; there is no `price` sort. | Sort on `COALESCE(price_after_discount, base_price)`. |
| f | 39-40, 258-259 | Loads all variant rows (`with('variants')`) **and** `withCount('variants')`; only the count is used. | `withCount` only. |
| g | 360-362 | `formatProductSummary` falls back to `$product->variants()->exists()` when no count is loaded. `show` (related ×4 + promotional ×4), `suggestions` (≤10) and `byIds` (unbounded) don't load the count → **one extra query per product**. | Always `withCount('variants')`. |
| h | 332-347 | `byIds`: no cap on id count, no int validation. | `ids: array|max:50`, `ids.*: integer`. |
| i | 145-156 | Reviews block duplicated with `ProductController` (different default: 4.5 vs 0.0 — P0 1.4); cross-plugin branch. | ProductReviews contributes `rating` / `reviews_count` (a `withAvg`/`withCount` scope it registers, or a `ProductDetailsContributor`). |
| j | 385 | `'rating' => 4.5` hard-coded in every summary → every card shows 4.5★. | Remove, or use the contributed value. |
| k | 169-176 | "Promotional products" has no ordering → arbitrary 4 items; always queried even if the theme doesn't show them. | Explicit order; fetch on demand. |
| l | 239-244 | Product sent **twice** (`data` and `product`). The TS type only declares `data`; two callers use `product \|\| data`. | Send `data` only; drop the fallback. |
| m | 105-112 | `filters` echoes the raw query back; nothing in `resources/js` reads it. | Remove. |
| n | 208-210 vs 221-223 | Selection rules emitted twice: effective values inside `extra_option` **and** raw overrides at top level. The "product overrides option" rule is also implemented in `ProductValidator::selectionRules()` → business rule in two places. | One `Product::effectiveSelectionRules()`; emit once. |
| o | 186, 374 | `price_after_discount ?? base_price` recomputed — `Product` already appends a `price` accessor (`Product.php:34-36,66-69`). Also in `CartPriceCalculator`, `ProductController` ×2. | Use `$product->price`. |
| p | 251-271 | `sections` loads **every** active product of every section, no limit. | Limit per section. |
| q | 17-19 | `PluginManager` injected only to ask about another plugin. | Goes away with (i). |

### 9.3 Unnecessary mapping: `CartItemFormatter::formatItem`, `CatalogProductsApiController`, `ProductController`

**The same `Product` is hand-mapped to an array in 5 places, with 4 different shapes:**

| Mapper | Shape |
|---|---|
| `CatalogProductsApiController::show` (`:178-237`) | `extra_option`, `weight_option`, `reviews_count`; nested fields re-cast one by one |
| `CatalogProductsApiController::formatProductSummary` (`:358-387`) | adds `has_variants/has_extras/has_options`, fake `rating` |
| `ProductController::show` (`:46-85`) | **`extraOption`**, **`reviewsCount`**, raw `variants`/`items` models (all columns), `{product: …}` envelope |
| `ProductController::getByIds` (`:104-117`) | `category` is a **string**, not an object; bare array response |
| `CartItemFormatter::formatItem` (`:32-62`) | product subset + full `weight_option.values`; no `category_id` |

**`ProductController` is not routed anywhere** (checked `routes/` and every plugin `routes/tenant.php`). Its 121 lines are dead, but its camelCase keys are still what the frontend expects (`useProductOptions`, `ProductOptions`, `ProductInfo.reviewsCount`), while the live controller sends snake_case. The frontend carries fallbacks for both shapes (`extra_option || extraOption`, `weight_option || weightOption`, `product || data`, `related_products || relatedProducts`). The full product page adapts the keys; the quick-add modal doesn't → P0 1.11. `reviewsCount` is never sent by the live API, so review counts never render.

**Mapping that adds nothing:**
- Field-by-field copies of attributes the models already cast: `(float) base_price`, `(bool) is_active`, `(bool) sell_by_weight`, `(float) $item->price`, `(bool) is_default`, `(bool) allow_quantity` (`Product.php:76-87`, `ExtraOptionItem.php:119-127`).
- `price` derived 4 times instead of using `Product`'s appended `price` accessor.
- `category` sub-arrays re-selecting the same columns the eager load already restricted (`category:id,name,name_ar`).
- `CartItemFormatter` fields with no reads in `resources/js`: `product.base_price`, `product.price_after_discount`, `variant.price`, top-level `variant_id`, and top-level `weight_option_value_id` (duplicates `weight_option_value.id`; `ThemedCart.tsx:144` falls back between the two).
- `formatItem` computes price, extras total and quantity, then calls `calculateSubtotal()`, which computes all three again.
- The product's full `weight_option.values` list is repeated on **every cart line** (only the weight picker needs it).

**Why it matters beyond duplication: the formatter output became a domain contract by accident.** `CartService::getCart()` returns the *presentation* array, and the same array is the input to:
- `OrderValidationContext` / `PricingCalculationContext` (`PlaceOrderService.php:34-44`);
- `OrderRepository::createOrderItems` (`OrderRepository.php:126-188`), which also reads `extra_option_item` fallbacks the formatter never produces (dead branches at `:181-183`);
- `CouponService`, which expects `product.category_id` — not produced → P0 1.10.

So changing a JSON field for the UI silently changes order creation and coupon behaviour, and a missing UI field has already broken category coupons.

**Recommended shape:**

1. **Catalog owns its representation:** `ProductSummaryResource`, `ProductResource`, `VariantResource`, `ExtraOptionResource`, `WeightOptionResource` in `CatalogProducts/src/Http/Resources`. One key style (snake_case), one place. ProductReviews adds `rating` / `reviews_count` through an extension hook instead of `isPluginActive` in Catalog.
2. **Delete `ProductController`.** Normalize the frontend once, in `useCatalogProduct` (read `extra_option` / `reviews_count`), and drop the per-component fallbacks.
3. **Split the cart into domain vs. presentation:**
   - `CartService::getLines(): CartLine[]` — readonly DTOs (`productId`, `categoryId`, `variantId`, `quantity`, `unitPrice`, `extras: CartLineExtra[]`, `subtotal`, name snapshots). `PlaceOrderService`, `OrderRepository` and the coupon pipes consume this.
   - `CartResource` / `CartItemResource` (HTTP only) built from `CartLine[]`, reusing Catalog's `WeightOptionResource` for the picker.
   - `CouponService` takes `CartLine[]` instead of `array $cartItems`, so a missing field is a type error, not a silent `isset() === false`.
4. **Coupon preview** (`CouponApiController::validate`) should use the server-side cart instead of trusting client `cart_items` and `sub_total`, so preview and checkout can't disagree.

A 6th product mapper lives in `CustomerFavoritesApiController::index` (`:46-64`) — it should reuse Catalog's `ProductSummaryResource` too.

---

## 10. Layering & coupling violations — full sweep

Three rules every plugin should follow, and every place in `app/Plugins` that breaks them today:

1. **Controllers are thin.** Input validation and authorization belong in a `FormRequest`, not in the action.
2. **A plugin knows only the core and the plugins in its `dependencies`.** It never names an *optional* plugin — not by id string, class, relation, container alias, column, or field shape. Optional features plug into **extension points owned by the host plugin**.
3. **Depend on abstractions across plugin boundaries.** Another plugin's services are consumed through an interface that plugin publishes; another plugin's tables are never written to directly.

### 10.1 Validation inside controllers (rule 1)

Only **3 FormRequests exist** in the whole plugin tree (`CartEngine` ×2, `OrderManagement/OrderStatusUpdateRequest`). Everything else validates inline or not at all.

| Controller action | Lines | What's inline | Fix |
|---|---|---|---|
| `OrderController::placeOrder` | `OrderManagement/.../OrderController.php:140-183` | `Validator::make` with **34 rules**; manual `fails()` → hand-built 422 with a `{success, errors}` envelope that differs from Laravel's; rules branch on `Auth::check()`; enum lists copied as strings; 11 `guest_data.*` rules that belong to GuestCheckout; 11 `billing_data.*` rules that belong to the payment flow. | `PlaceOrderRequest` (OrderManagement) with only order rules + `Rule::enum`. Guest and billing rules contributed by their plugins (see §10.2 design). |
| `GuestCheckoutApiController::track` | `GuestCheckout/.../GuestCheckoutApiController.php:35-45` | `Validator::make` + manual 422 (third envelope style). | `TrackOrderRequest`. |
| `LocationsDeliveryApiController::storeAddress` / `updateAddress` | `LocationsDelivery/.../LocationsDeliveryApiController.php:122-136`, `167-181` | The **same 9 rules + custom message copy-pasted twice**; Egyptian phone regex hard-coded; manual `401`/`403` ownership checks in the action (`:113-120`, `:158-165`). | One `AddressRequest` (rules) + `AddressPolicy` (`authorize()`); route-model binding scoped to the user. |
| `CouponApiController::validate` | `CouponsPromotions/.../CouponApiController.php:25-31` | Inline rules; accepts client `cart_items` + `sub_total` and trusts them (§9.3); `address_id` not checked for ownership. | `ValidateCouponRequest`; read the cart server-side. |
| `ProductReviewsApiController::store` | `ProductReviews/.../ProductReviewsApiController.php:49-55` | Inline rules branching on `auth()->check()`; file storage done in the action. | `StoreReviewRequest`; image storage in a service (MediaOptimizer already exists for this). |
| `CustomerFavoritesApiController::sync` / `store` | `CustomerFavorites/.../CustomerFavoritesApiController.php:82, 101` | Inline rules — and the actions **do nothing**: they echo the validated input back without persisting anything. | Delete the endpoints, or implement them. |
| `CatalogProductsApiController` (5 actions) | `CatalogProducts/.../CatalogProductsApiController.php` | **16 unvalidated** `$request->get()/input()` reads (`per_page`, `sort_by`, `category`, `min_price`, `ids`, `limit`, …) → P0 1.12. | `ProductIndexRequest`, `ProductSuggestionsRequest`, `ProductsByIdsRequest`. |
| `PaymentProcessingApiController`, `PaymentController`, `PaymobWebhookController`, `CmsPagesApiController`, `PackageApiController`, `NotificationController` | 1 raw read each | Unvalidated query input. | Small FormRequests, or typed route params. |

### 10.2 Plugins that know about plugins they don't depend on (rule 2)

#### OrderManagement → GuestCheckout (most severe: 9 files)

GuestCheckout depends on OrderManagement, so OrderManagement must not know guests exist. Today it knows guest **rules, service alias, routes, session keys, table, columns, relation and field names**:

| Where | What OrderManagement knows about GuestCheckout |
|---|---|
| `OrderController.php:150-161` | Guest request shape (`guest_data.*`, 11 rules). |
| `OrderController.php:189` | Plugin id `'guest-checkout'` via `isPluginActive`. |
| `OrderController.php:196-204` | Container alias `'guest.user.service'` + its `findOrCreate()` method. |
| `OrderController.php:233` | Guest session key `guest_order_ids`. |
| `OrderController.php:238-244` | "Guest = not a `User`" test; GuestCheckout's route `/orders/track`. |
| `PlaceOrderData.php:16, 26-29` | `public object $user` (untyped) + `isGuest()` = `! instanceof User`. |
| `CalculateDeliveryFeePipe.php:28-31` | Reads `$user->area_id` — a `GuestUser` column. |
| `OrderPaymentDispatcher.php:76-89` | Reads `GuestUser` fields (`street`, `building`, `floor`, `apartment`, `city`, `full_phone`). |
| `OrderRepository.php:72-73` | Writes `guest_user_id`. |
| `Order.php:44, 56-63, 95-121, 163-166` | `guest_user_id` column, `guestUser` relation, 3 accessors + `guestCheckoutActive()` via plugin id. |
| `Filament/.../OrdersTable.php:46-60` | Plugin id ×2; searches `guestUser`; sorts with a `UNION` on the `GuestUser` **model class** and `guest_users` table. |
| `Filament/.../OrderInfolist.php:87-120` | Plugin id ×3; renders guest address from `GuestUser` fields. |

**Target design (the same pattern fixes every row below):**

- **Core contract `Customer`** (`app/Core/Contracts`): `id()`, `name()`, `phone()`, `email()`, `isGuest()`, `deliveryAreaId()`, `billingAddress()`. Implemented by `User` and by `GuestUser`. Replaces today's `GuestCustomer` (only `getKey()`) and `PlaceOrderData::$user` typed as `object`.
- **OrderManagement owns `CustomerResolver`** (`resolve(Request): ?Customer`). Default binding = authenticated user only. GuestCheckout **decorates** the binding to fall back to `guest_data` — OrderController never sees the word "guest".
- **OrderManagement owns `PlaceOrderRulesContributor`** (tagged). `PlaceOrderRequest::rules()` merges contributed rules; GuestCheckout contributes `guest_data.*`, PaymentProcessing contributes `billing_data.*`.
- **Snapshot customer data at placement** (`customer_name`, `customer_phone`, `delivery_address` from `Customer`) — then `Order` accessors, the admin table and the infolist no longer need `guestUser` at all.
- **After-placement behaviour via events**: OrderManagement fires `OrderPlaced`; GuestCheckout's listener handles session/tracking. The response carries `order_number` (+ a tracking token if GuestCheckout added one through a response contributor); the frontend builds routes.
- **Admin UI extension point**: `OrderResourceExtender` (tagged) lets GuestCheckout add its infolist entry / search clause.

#### Every other occurrence

| From → To | Declared? | Where | Instead |
|---|---|---|---|
| OrderManagement → CouponsPromotions | ✗ (cycle) | `OrderRepository.php:59-68` — plugin id + `Coupon::find()` | Coupons puts `coupon_id`/`code` on the validation context / a pricing adjustment; repository reads only the context. |
| OrderManagement → LocationsDelivery | ✓ | `OrderRepository.php:43` — `isPluginActive('locations-delivery')` on a hard dependency | Delete the check. |
| CouponsPromotions → GuestCheckout | ✗ | `CouponService.php:198-201, 340-347, 372-379` — plugin id + `guestUser` relation + guest `phone` column | Query by the snapshotted `customer_phone` only (works once orders snapshot it), or an OrderManagement `CustomerOrderHistory` contract. |
| PosIntegration → GuestCheckout | ✗ | `POSCustomerDataFormatter.php:25, 60-…` — `$order->guestUser` + `GuestUser` fields | Use the order's customer snapshot / `Customer` contract. |
| **PaymentProcessing → PosIntegration** | ✗ | `PaymentStateMachine.php:93-95` — sets `pos_status = READY` when payment completes. Payment decides when POS sends. | Fire `OrderPaymentCompleted`; PosIntegration listens and marks its own state. |
| CatalogProducts → ProductReviews | ✗ | `CatalogProductsApiController.php:148-156`, `ProductController.php:37-45` — plugin id, `reviews()` relation, `is_approved`/`is_published` columns | ProductReviews contributes rating data (resource extension / `withAvg` scope it registers). |
| CatalogProducts → PosIntegration | ✗ (hidden cycle) | `ProductForm.php:140-170`, `ExtraOptionForm.php:94-122` (POS tab + `PosItemSelect`), `ExtraOptionItem.php:22` (`pos_mapping_type`) | `ProductFormExtender` extension point; POS owns its tab and column. |
| LocationsDelivery → OrderManagement | ✗ (cycle) | `Branch.php:26-33` — `orders()` by class-name string | Delete; OrderManagement already registers the relation. |
| OrderManagement (`OrderPosStatus` enum, `pos_*` columns) ← POS concepts | — | `Order.php`, `OrderRepository.php:107` (`finalizeOfflineOrder` sets `pos_status`) | OrderManagement fires `OrderFinalized`; POS owns `pos_status`. |

### 10.3 Concretions instead of abstractions (rule 3)

#### (a) An interface exists, but the concrete class is injected

| Concrete | Interface that already exists | Injected at |
|---|---|---|
| `CartEngine\…\CartService` | `CartServiceInterface` (bound in `CartEngineServiceProvider`) | **Cross-plugin:** `OrderController.php:11,35`, `PlaceOrderService.php:7,24`, `OrderRepository.php:10,25`. **Same plugin:** `CartEngineApiController.php:16`, `SyncGuestCartOnLogin.php:16`. |
| `PaymentProcessing\…\KashierService` | `PaymentGatewayInterface` (via `PaymentGatewayFactory`) | `PaymentProcessingApiController.php:17` — bypasses the gateway abstraction entirely. |
| `POSProductResolver` (via `method_exists`) | `POSProductResolverInterface` | `OrderPOSPayloadBuilder.php:37,50` — calls a method the interface doesn't declare (effectively concrete). |

#### (b) No interface exists, so every consumer is forced onto a concrete class

| Concrete | Consumers | Recommended abstraction |
|---|---|---|
| `StoreSettings\…\SettingService` | **12 plugins / ~55 references** (OrderManagement 18, CatalogProducts 11, PosIntegration 5, ProductReviews 4, PaymentProcessing 4, 7 others ×2) + 3 core files; also a string alias `'setting.service'`. 6 of those plugins don't even declare the dependency. | `SettingsRepository` contract in `app/Core/Contracts`; StoreSettings binds it. Each plugin reads its own typed settings object. |
| `OrderManagement\…\Pipelines\OrderPipelineRegistry` | CouponsPromotions, GuestCheckout, PaymentProcessing resolve the concrete `final` class with `app()` in `bootForTenant`. | `OrderPipelineRegistrar` interface — or better, declare pipes in `plugin.json` (`"order_pipes": {"validation": {...}, "pricing": {...}}`) so the kernel registers them and plugins never resolve OrderManagement classes. |
| `OrderValidationContext`, `PricingCalculationContext`, `PricingAdjustment` | Used by 3 plugins' pipes. | Fine as DTOs, but they are OrderManagement's **public API**: move them to `src/Contracts` (or `src/Api`), make them stable, and stop exposing the free-form `attributes()` bag that Coupons uses to smuggle `applied_coupon`. |
| `Core\Plugins\Services\PluginManager` | Injected into plugin **domain** code to ask "is plugin X on?": `CatalogProductsApiController.php:18`, `ProductController.php:16`, `CouponService.php:15`, `OrderRepository.php:26`, `SyncGuestCartOnLogin.php:17`; `app(PluginManager::class)` in `Order.php:165`, `OrderController.php:189`, `OrdersTable.php:46,52`, `OrderInfolist.php:87,98,114`. | The kernel's management service shouldn't be a plugin dependency. Every call site disappears once the extension points in §10.2 exist. |
| `CouponService`, `ProductPOSImporterService`, `PaymentGatewayFactory`, `HeroBannerService`, `IntegrationTokenService` | Injected concretely within their own plugin (`CouponService` ×4 sites, `ProductPOSImporterService` also via `app()` in `PosItemSelect`). | Lower priority (same plugin). Worth an interface where it's a seam: `ProductPOSImporterService` (HTTP to the POS master) and `PaymentGatewayFactory` (gateway registry). |

#### (c) Reaching into another plugin's Eloquent models

**81 cross-plugin model references** from 11 plugins. The plugins everyone depends on publish **no interfaces at all**: CatalogProducts, LocationsDelivery, StoreSettings, CouponsPromotions, GuestCheckout. So consumers go straight to the models.

| Provider plugin | Inbound model refs | From |
|---|---|---|
| CatalogProducts (`Product`, `ProductVariant`, `ExtraOptionItem`, `Category`, `WeightOptionValue`) | ~30 | ComboPackages, ProductReviews, PosIntegration, OrderManagement, CartEngine, CouponsPromotions, CustomerFavorites |
| OrderManagement (`Order`, `OrderItem`) | ~24 | PosIntegration 9, PaymentProcessing 6, GuestCheckout 3, CouponsPromotions 3, PushNotifications 2, LocationsDelivery 1 |
| LocationsDelivery (`Address`, `Branch`, `Area`, `Governorate`) | ~24 | PosIntegration 8, OrderManagement 10, CouponsPromotions 4, GuestCheckout 1 |

Reading a declared dependency's model for display is tolerable. **Writing to or querying business rules on another plugin's tables is not:**

| Where | Cross-plugin write / query |
|---|---|
| `ProductPOSImporterService.php:333-353, 430` | PosIntegration **creates** `Category` + `Product` rows and **updates** product prices — bypasses Catalog's rules, events and media handling. → Catalog `ProductImporter` / `ProductPriceUpdater` contract. |
| `PaymentStateMachine.php:97` | PaymentProcessing **updates** `Order` columns (`payment_status`, `transaction_id`, `payment_data`, `pos_status`). → OrderManagement `OrderPaymentRecorder` contract (or events). |
| `CouponService.php:329-384` | Coupons **queries** `Order` with hard-coded status strings to decide usage limits. → OrderManagement `CustomerOrderHistory::countCompletedOrders(Customer, ?couponId)`. |
| `OrderRepository.php:44`, `CalculateDeliveryFeePipe.php:29-33`, `OrderPaymentDispatcher.php:92`, `OrderController.php:113-121` | OrderManagement queries `Address`, `Area`, `Branch`, `Governorate` directly (including a duplicate of LocationsDelivery's own checkout query). → LocationsDelivery `DeliveryQuote` / `AddressBook` contract. |

**Recommendation:** each provider plugin publishes a small `src/Contracts` surface (read models + the 2–3 commands others need), and the boundary CI check from §8 step 2 also flags **any** cross-plugin `Models\` import outside an allow-list that shrinks over time.
