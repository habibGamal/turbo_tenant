# Phase 4 Migration Plan: Operational Addons & Merchandising

> **Milestone:** M4  
> **Plugins:** `pos-integration`, `coupons-promotions`, `push-notifications`, `combo-packages`  
> **Scope:** Kitchen/POS synchronization, discount rules, marketing push alerts, and meal package bundles  
> **Target Path:** `.plan/migration_to_plugins/04_phase4_operational_addons_and_merchandising.md`  

---

## 1. Objectives & Overview

Phase 4 packages operational store workflows and promotional merchandising tools into independent modules:
1. **`pos-integration`**: Synchronizes orders and shifts to local POS/kitchen hardware.
2. **`coupons-promotions`**: Discount engine injecting into `PricingCalculationPipeline`.
3. **`push-notifications`**: Customer notifications via Expo Push and bulk marketing campaigns.
4. **`combo-packages`**: Group deals and bundle meal packages with flexible item selection.

---

## 2. Plugin 8: `pos-integration`

### 2.1 Manifest Specification (`app/Plugins/PosIntegration/plugin.json`)
```json
{
  "id": "pos-integration",
  "name": "Point of Sale (POS) Integration",
  "version": "1.0.0",
  "category": "addon",
  "description": "Bi-directional POS synchronization for orders, kitchen tickets, product mapping, and branch shifts.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "order-management",
    "catalog-products"
  ],
  "providers": [
    "App\\Plugins\\PosIntegration\\PosIntegrationServiceProvider"
  ],
  "filament": "App\\Plugins\\PosIntegration\\src\\Filament\\PosIntegrationFilamentPlugin"
}
```

### 2.2 Relocated Files
- **Models:** `app/Models/ProductPosMapping.php` $\to$ `app/Plugins/PosIntegration/src/Models/ProductPosMapping.php`.
- **Services:** `OrderPOSService.php`, `ProductPOSImporterService.php`, `app/Services/POS/*`.
- **Migrations:**
  - `2025_11_12_195712_create_product_pos_mappings_table.php`
  - `2025_11_20_175857_add_pos_mapping_type_and_quantity_to_extra_option_items_table.php`
  - `2025_11_28_151428_add_pos_status_to_orders_table.php`

---

## 3. Plugin 9: `coupons-promotions`

### 3.1 Manifest Specification (`app/Plugins/CouponsPromotions/plugin.json`)
```json
{
  "id": "coupons-promotions",
  "name": "Coupons & Promotional Discounts",
  "version": "1.0.0",
  "category": "marketing",
  "description": "Discount codes, percentage/fixed cuts, usage limits, and conditional promotion rules.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "cart-engine"
  ],
  "providers": [
    "App\\Plugins\\CouponsPromotions\\CouponsPromotionsServiceProvider"
  ],
  "filament": "App\\Plugins\\CouponsPromotions\\src\\Filament\\CouponsPromotionsFilamentPlugin"
}
```

### 3.2 Relocated Files & Pipeline Pipe
- **Models:** `app/Models/Coupon.php`.
- **Services:** `app/Services/CouponService.php`.
- **Controllers & Routes:** `app/Http/Controllers/CouponController.php` (route `/coupons/validate`).
- **Filament Resources:** `app/Filament/Resources/Coupons/`.
- **Migrations:** `2025_11_12_195721_create_coupons_table.php`, `2025_11_23_201139_add_conditions_to_coupons_table.php`.
- **Pricing Pipe:** Registers `ApplyCouponDiscountPipe` into host `PricingCalculationPipeline`.

---

## 4. Plugin 10: `push-notifications`

### 4.1 Manifest Specification (`app/Plugins/PushNotifications/plugin.json`)
```json
{
  "id": "push-notifications",
  "name": "Push & Bulk Notifications",
  "version": "1.0.0",
  "category": "communication",
  "description": "Send bulk marketing pushes and transactional Expo push alerts to mobile and web subscribers.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [],
  "providers": [
    "App\\Plugins\\PushNotifications\\PushNotificationsServiceProvider"
  ],
  "filament": "App\\Plugins\\PushNotifications\\src\\Filament\\PushNotificationsFilamentPlugin"
}
```

### 4.2 Relocated Files
- **Models:** `BulkNotification.php`, `UserNotification.php`.
- **Filament Resources:** `app/Filament/Resources/BulkNotifications/`.
- **Controllers & Routes:** `app/Http/Controllers/Api/NotificationController.php` (routes `/api/notifications/*`).
- **Migrations:** Expo token user migration and bulk notifications migrations.

---

## 5. Plugin 11: `combo-packages`

### 5.1 Manifest Specification (`app/Plugins/ComboPackages/plugin.json`)
```json
{
  "id": "combo-packages",
  "name": "Combo Meals & Packages",
  "version": "1.0.0",
  "category": "marketing",
  "description": "Meal bundles, combo group selections, and promotional value meals.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "catalog-products"
  ],
  "providers": [
    "App\\Plugins\\ComboPackages\\ComboPackagesServiceProvider"
  ],
  "filament": "App\\Plugins\\ComboPackages\\src\\Filament\\ComboPackagesFilamentPlugin"
}
```

### 5.2 Relocated Files
- **Models:** `Package.php`, `PackageGroup.php`, `PackageItem.php`.
- **Filament Resources:** `app/Filament/Resources/Packages/`.
- **Migrations:** `2025_11_19_190457_create_packages_table.php`, `2025_11_23_193558_create_package_items_table.php`.

---

## 6. Phase 4 Verification Checklist
1. **POS Dispatch Isolation:** If `pos-integration` is deactivated, order completion does not trigger POS HTTP requests.
2. **Coupon Calculation:** Apply valid coupon $\to$ Cart reflects discount. If `coupons-promotions` is deactivated, coupon input box vanishes from cart/checkout.
3. **Push Alerts:** Send bulk notification from Filament $\to$ recipients receive push alerts.
4. **Combo Meal Selection:** Create package in Filament $\to$ view package on storefront.
