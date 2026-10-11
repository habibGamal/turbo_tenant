# Phase 1 Migration Plan: Core Foundation Plugins

> **Milestone:** M1  
> **Plugins:** `store-settings` (Core), `locations-delivery`, `catalog-products`  
> **Scope:** Fundamental store configuration, geography/delivery boundaries, and product catalog  
> **Target Path:** `.plan/migration_to_plugins/01_phase1_core_foundation_plugins.md`  

---

## 1. Objectives & Overview

Phase 1 migrates the three essential foundation systems upon which all other commerce activities depend. 

Specifically:
- **`store-settings`** is established as a **permanent core plugin** (`"is_core": true`). It cannot be deactivated or uninstalled by any tenant.
- **`locations-delivery`** packages geographic structures (branches, governorates, delivery areas, customer addresses).
- **`catalog-products`** packages product taxonomy, variants, weights, extra options, and curated showcase sections.

---

## 2. Plugin 1: `store-settings` (Core Plugin)

### 2.1 Manifest Specification (`app/Plugins/StoreSettings/plugin.json`)
```json
{
  "id": "store-settings",
  "name": "Store Settings & Configuration",
  "version": "1.0.0",
  "category": "foundation",
  "description": "Essential store profile, currency, business hours, and operational configuration.",
  "author": "Turbo Tenant Team",
  "is_core": true,
  "dependencies": [],
  "providers": [
    "App\\Plugins\\StoreSettings\\StoreSettingsServiceProvider"
  ],
  "filament": "App\\Plugins\\StoreSettings\\src\\Filament\\StoreSettingsFilamentPlugin"
}
```

### 2.2 Core Enforcement Rules
- In `PluginLifecycleService`, any attempt to deactivate or uninstall `store-settings` throws:
  ```php
  if ($manifest->isCore) {
      throw new RuntimeException("Plugin '{$pluginId}' is a mandatory core plugin and cannot be deactivated or uninstalled.");
  }
  ```
- When a new tenant is created via `TenancyServiceProvider`, `store-settings` is automatically activated.

### 2.3 Files to Migrate & Relocate
| Original Location | Destination in Plugin | Action |
| :--- | :--- | :--- |
| `app/Models/Setting.php` | `app/Plugins/StoreSettings/src/Models/Setting.php` | Move model |
| `app/Services/SettingService.php` | `app/Plugins/StoreSettings/src/Services/SettingService.php` | Move service |
| `database/migrations/tenant/2025_11_21_143303_create_settings_table.php` | `app/Plugins/StoreSettings/database/migrations/tenant/2025_11_21_143303_create_settings_table.php` | Relocate to plugin |
| Settings Filament Pages in `app/Filament/Pages/` | `app/Plugins/StoreSettings/src/Filament/Pages/` | Move pages |

---

## 3. Plugin 2: `locations-delivery`

### 3.1 Manifest Specification (`app/Plugins/LocationsDelivery/plugin.json`)
```json
{
  "id": "locations-delivery",
  "name": "Locations, Branches & Delivery Areas",
  "version": "1.0.0",
  "category": "foundation",
  "description": "Management of physical branches, delivery governorates, service areas, and customer delivery addresses.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "store-settings"
  ],
  "providers": [
    "App\\Plugins\\LocationsDelivery\\LocationsDeliveryServiceProvider"
  ],
  "filament": "App\\Plugins\\LocationsDelivery\\src\\Filament\\LocationsDeliveryFilamentPlugin"
}
```

### 3.2 Files to Migrate & Relocate
| Original Location | Destination in Plugin | Action |
| :--- | :--- | :--- |
| `app/Models/Branch.php` | `app/Plugins/LocationsDelivery/src/Models/Branch.php` | Move model |
| `app/Models/Governorate.php` | `app/Plugins/LocationsDelivery/src/Models/Governorate.php` | Move model |
| `app/Models/Area.php` | `app/Plugins/LocationsDelivery/src/Models/Area.php` | Move model |
| `app/Models/Address.php` | `app/Plugins/LocationsDelivery/src/Models/Address.php` | Move model |
| `app/Filament/Resources/Branches/` | `app/Plugins/LocationsDelivery/src/Filament/Resources/Branches/` | Move resource |
| `app/Filament/Resources/Governorates/` | `app/Plugins/LocationsDelivery/src/Filament/Resources/Governorates/` | Move resource |
| `app/Filament/Resources/Areas/` | `app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/` | Move resource |
| `app/Filament/Resources/Addresses/` | `app/Plugins/LocationsDelivery/src/Filament/Resources/Addresses/` | Move resource |
| `app/Http/Controllers/AddressController.php` | `app/Plugins/LocationsDelivery/src/Http/Controllers/AddressController.php` | Move controller |
| `database/migrations/tenant/2025_11_12_165645_create_branches_table.php` | `app/Plugins/LocationsDelivery/database/migrations/tenant/...` | Relocate |
| `database/migrations/tenant/2025_11_12_173912_create_governorates_table.php` | `app/Plugins/LocationsDelivery/database/migrations/tenant/...` | Relocate |
| `database/migrations/tenant/2025_11_12_173946_create_areas_table.php` | `app/Plugins/LocationsDelivery/database/migrations/tenant/...` | Relocate |
| `database/migrations/tenant/2025_11_12_195726_create_addresses_table.php` | `app/Plugins/LocationsDelivery/database/migrations/tenant/...` | Relocate |
| `resources/js/themes/default/components/AddressForm.tsx` | `app/Plugins/LocationsDelivery/resources/js/AddressForm.tsx` | Relocate component |
| `resources/js/themes/default/pages/Profile/Partials/AddressManager.tsx` | `app/Plugins/LocationsDelivery/resources/js/AddressManager.tsx` | Relocate component |

---

## 4. Plugin 3: `catalog-products`

### 4.1 Manifest Specification (`app/Plugins/CatalogProducts/plugin.json`)
```json
{
  "id": "catalog-products",
  "name": "Catalog, Products & Taxonomy",
  "version": "1.0.0",
  "category": "foundation",
  "description": "Categories, food menu items, product variants, weight pricing, extra modifiers, and curated sections.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "store-settings"
  ],
  "providers": [
    "App\\Plugins\\CatalogProducts\\CatalogProductsServiceProvider"
  ],
  "filament": "App\\Plugins\\CatalogProducts\\src\\Filament\\CatalogProductsFilamentPlugin"
}
```

### 4.2 Files to Migrate & Relocate
- **Models:** `Product.php`, `Category.php`, `ProductVariant.php`, `WeightOption.php`, `WeightOptionValue.php`, `ExtraOption.php`, `ExtraOptionItem.php`, `Section.php`, `SectionProduct.php`.
- **Filament Resources:** `Categories/`, `Products/`, `WeightOptions/`, `ExtraOptions/`, `Sections/`.
- **Controllers:** `ProductController.php`, `MenuController.php`, `SectionController.php`, `Api\ProductController.php`, `Api\ProductSearchController.php`.
- **Migrations:** All 16 category, product, option, variant, weight, and section tenant migrations relocated into `app/Plugins/CatalogProducts/database/migrations/tenant/`.
- **Frontend Views:** `ProductShow.tsx`, `MenuPage.tsx`, `Section/Show.tsx`, `CategoriesSection.tsx`, `ProductsSection.tsx`, `ProductCard.tsx`.

---

## 5. Phase 1 Verification Checklist
1. **Manifest Registration:** `php artisan plugin:discover` finds `store-settings`, `locations-delivery`, and `catalog-products`.
2. **Core Lock Verification:** Attempting `php artisan tenant:plugin:deactivate {tenant} store-settings` throws `RuntimeException: Plugin 'store-settings' is a mandatory core plugin`.
3. **Tenant Activation & Migration:** Activating `catalog-products` runs only catalog migrations in the tenant database.
4. **Digital Menu Showcase:** A tenant with only `catalog-products` enabled can render `/menu` and `/products/{id}` cleanly without cart or checkout dependencies.
