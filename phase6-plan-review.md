# Plan: Phase 6 Pure Microkernel Cutover (Zero Backwards-Compatibility)

> **Task Slug:** `phase6-plan-review`  
> **Status:** Planning Mode / Pending User Review  
> **Directive:** Absolute zero backwards-compatibility. Purge all 31 model shims, 15+ duplicate controllers, 40+ duplicate services, and all class_alias blocks. Transition directly to canonical plugin namespaces.

---

## 1. Zero Backwards-Compatibility Purge Scope
- **`app/Models/`**: Delete all 31 duplicate/shim domain models. Keep only: `User.php`, `Tenant.php`, `TenantPlugin.php`, `Plugin.php`.
- **`app/Http/Controllers/`**: Delete all 15 migrated domain controllers. Keep only host controllers (`Controller`, `ProfileController`, `Tenant*`, `Auth/*`).
- **`app/Services/`**: Delete all 40+ migrated domain services (`Cart/*`, `Order/*`, `POS/*`, `Payment/*`, `SettingService.php`, etc.). Keep only `ManifestService.php` and `TenantService.php`.
- **Plugin ServiceProviders**: Remove all `$aliases = [...]` and `class_alias` loops from `CmsPages`, `HeroBanners`, `CustomerFavorites`, `ComboPackages`, `ProductReviews`, `PushNotifications`.
- **`app/Filament/Resources/`**: Delete duplicate resources (`HeroSliderResource`, `BulkNotifications/`, `Packages/`, `Pages/`). Explicitly register `UserResource::class` in `AdminPanelProvider.php`.
- **`database/factories/` & `tests/`**: Update all model and service imports to direct plugin namespaces (`App\Plugins\{StudlyName}\src\...`).

---

## 2. Phase 6 Implementation Tasks (INPUT → OUTPUT → VERIFY)

### Task 1: Fix Plugin Architectural Violations (Pattern 1 & Dependencies)
- **Agent:** `backend-specialist`
- **INPUT:** `PaymentProcessingConfig.php`, `PosIntegrationConfig.php`, manifests.
- **OUTPUT:**
  - Strongly typed properties on `PaymentProcessingConfig` and `PosIntegrationConfig`.
  - ServiceProvider Composition Root bindings.
  - Inject DTOs into `PaymobService`, `KashierService`, `PaymentGatewayFactory`, and `ProductPOSImporterService`.
  - Fix manifest dependencies for `CouponsPromotions`, `GuestCheckout`, `OrderManagement`, `PosIntegration`, `PushNotifications`.
  - Decouple `OrdersTable.php` and `OrderController.php` from `GuestUser` class coupling.
- **VERIFY:** `php artisan plugin:discover` succeeds with 0 dependency errors.

### Task 2: Encapsulate Plugin Routes & Middleware
- **Agent:** `backend-specialist`
- **INPUT:** Domain routes from `routes/tenant.php`.
- **OUTPUT:**
  - Plugin-local `routes/tenant.php` for `CartEngine`, `CatalogProducts`, `LocationsDelivery`, `OrderManagement`, `GuestCheckout`, `PaymentProcessing`, and `CouponsPromotions`.
  - ServiceProviders call `$this->loadRoutesFrom(__DIR__.'/routes/tenant.php')`.
  - `EnsureCheckoutAccessMiddleware` created and bound to `/checkout`.
- **VERIFY:** `php artisan route:list` shows all plugin routes registered under tenant domain.

### Task 3: Monolith Purge & Host Decoupling
- **Agent:** `backend-specialist`
- **INPUT:** `app/Models/`, `app/Http/Controllers/`, `app/Services/`, `app/Filament/Resources/`, `AdminPanelProvider.php`, `routes/tenant.php`.
- **OUTPUT:**
  - Delete 31 legacy models from `app/Models/`.
  - Delete 15 legacy controllers from `app/Http/Controllers/`.
  - Delete 40+ legacy services from `app/Services/`.
  - Delete duplicate Filament resources.
  - Remove all `class_alias` blocks in plugin ServiceProviders.
  - `AdminPanelProvider.php` stripped of auto-discovery and injects active plugins via `PluginManager::getActiveFilamentPluginsForTenant(tenant())`.
  - `routes/tenant.php` stripped to host shell only.
- **VERIFY:** `app/Models/` has only 4 host models; `AdminPanelProvider` compiles cleanly.

### Task 4: Factories & Tests Modernization
- **Agent:** `backend-specialist` & `test-engineer`
- **INPUT:** `database/factories/` and `tests/`.
- **OUTPUT:**
  - All factories updated to use `App\Plugins\...` model classes.
  - All tests updated to import `App\Plugins\...` classes.
  - Delete obsolete `OrderPaymentDispatcherTest.php`.
- **VERIFY:** Factories create plugin model instances properly.

### Task 5: Production Grandfathering Seeder & Suite Green
- **Agent:** `backend-specialist`
- **INPUT:** `GrandfatherExistingTenantsPluginsSeeder.php` specification.
- **OUTPUT:**
  - Seeder created and executed.
  - Full Pest test suite verified green.
- **VERIFY:**
  - `php artisan db:seed --class=GrandfatherExistingTenantsPluginsSeeder`
  - `php -d memory_limit=512M artisan test` passes 100% green.

---

## 3. Phase X: Final Verification Checklist
- [ ] Exactly 4 models in `app/Models/` (`User`, `Tenant`, `TenantPlugin`, `Plugin`)
- [ ] No domain controllers in `app/Http/Controllers/`
- [ ] No domain services in `app/Services/`
- [ ] No `class_alias` calls in any plugin ServiceProvider
- [ ] `php artisan plugin:discover` succeeds with all 15 plugins
- [ ] `php artisan db:seed --class=GrandfatherExistingTenantsPluginsSeeder` succeeds
- [ ] Full automated test suite passes with 100% green assertions
