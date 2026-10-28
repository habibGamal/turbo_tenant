# Phase 1: Global Navigation Restructuring & Filament Enum Contracts

> **Phase Target:** Core UI Architecture & Navigation Cleanliness  
> **Estimated Effort:** 45 minutes  
> **Verification Command:** `php artisan filament:check` / `php artisan test`  

---

## 1. Objectives

1. Group all 18 resources and custom pages into 7 intuitive sidebar navigation groups.
2. Hide [AddressResource.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Addresses/AddressResource.php) from navigation (`$shouldRegisterNavigation = false`).
3. Rename [HeroSliderResource.php](file:///e:/turbo_tenant/app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php) navigation and model labels from "سلايدر الترويسة" to **"سلايدر"**.
4. Upgrade enums to implement Filament's contracts (`HasLabel`, `HasColor`, `HasIcon`) to automatically render native badges and labels without repetitive table closure matching.

---

## 2. File Modification Specifications

### A. Navigation Group & Order Setup

Apply the following navigation groups and sort orders to all resources:

| Resource / Page | File | Navigation Group | Label | Navigation Sort |
| :--- | :--- | :--- | :--- | :---: |
| **Orders** | [OrderResource.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/OrderResource.php) | `إدارة المبيعات` | الطلبات | 1 |
| **Products** | [ProductResource.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Products/ProductResource.php) | `الكتالوج والمنتجات` | المنتجات | 1 |
| **Categories** | [CategoryResource.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Categories/CategoryResource.php) | `الكتالوج والمنتجات` | الفئات | 2 |
| **Extra Options** | [ExtraOptionResource.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/ExtraOptions/ExtraOptionResource.php) | `الكتالوج والمنتجات` | خيارات إضافية | 3 |
| **Weight Options** | [WeightOptionResource.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/WeightOptions/WeightOptionResource.php) | `الكتالوج والمنتجات` | خيارات الوزن | 4 |
| **Sections** | [SectionResource.php](file:///e:/turbo_tenant/app/Plugins/CatalogProducts/src/Filament/Resources/Sections/SectionResource.php) | `الكتالوج والمنتجات` | أقسام الواجهة | 5 |
| **POS Import** | [ImportPOSProducts.php](file:///e:/turbo_tenant/app/Plugins/PosIntegration/src/Filament/Pages/ImportPOSProducts.php) | `الكتالوج والمنتجات` | استيراد منتجات POS | 6 |
| **POS Price Sync**| [SyncPOSPrices.php](file:///e:/turbo_tenant/app/Plugins/PosIntegration/src/Filament/Pages/SyncPOSPrices.php) | `الكتالوج والمنتجات` | مزامنة الأسعار | 7 |
| **Coupons** | [CouponResource.php](file:///e:/turbo_tenant/app/Plugins/CouponsPromotions/src/Filament/Resources/Coupons/CouponResource.php) | `العروض والخصومات` | الكوبونات | 1 |
| **Packages** | [PackageResource.php](file:///e:/turbo_tenant/app/Plugins/ComboPackages/src/Filament/Resources/Packages/PackageResource.php) | `العروض والخصومات` | باقات التوفير | 2 |
| **Branches** | [BranchResource.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Branches/BranchResource.php) | `التوصيل والمناطق` | الفروع | 1 |
| **Governorates** | [GovernorateResource.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Governorates/GovernorateResource.php) | `التوصيل والمناطق` | المحافظات | 2 |
| **Areas** | [AreaResource.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Areas/AreaResource.php) | `التوصيل والمناطق` | المناطق | 3 |
| **Addresses** | [AddressResource.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Addresses/AddressResource.php) | *None* (`$shouldRegisterNavigation = false`) | — | — |
| **Users** | [UserResource.php](file:///e:/turbo_tenant/app/Filament/Resources/Users/UserResource.php) | `العملاء والتقييمات` | المستخدمين | 1 |
| **Guest Users** | [GuestUserResource.php](file:///e:/turbo_tenant/app/Plugins/GuestCheckout/src/Filament/Resources/GuestUsers/GuestUserResource.php) | `العملاء والتقييمات` | زوار المتجر | 2 |
| **Reviews** | [ReviewResource.php](file:///e:/turbo_tenant/app/Plugins/ProductReviews/src/Filament/Resources/ReviewResource.php) | `العملاء والتقييمات` | التقييمات والآراء | 3 |
| **Slider** | [HeroSliderResource.php](file:///e:/turbo_tenant/app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php) | `المحتوى والتسويق` | **سلايدر** | 1 |
| **Pages** | [PageResource.php](file:///e:/turbo_tenant/app/Plugins/CmsPages/src/Filament/Resources/Pages/PageResource.php) | `المحتوى والتسويق` | صفحات CMS | 2 |
| **Notifications**| [BulkNotificationResource.php](file:///e:/turbo_tenant/app/Plugins/PushNotifications/src/Filament/Resources/BulkNotifications/BulkNotificationResource.php) | `المحتوى والتسويق` | إشعارات Push | 3 |
| **Settings** | [Settings.php](file:///e:/turbo_tenant/app/Plugins/StoreSettings/src/Filament/Pages/Settings.php) | `الإعدادات والنظام` | إعدادات المتجر | 1 |
| **Plugins** | [ManagePlugins.php](file:///e:/turbo_tenant/app/Filament/Pages/ManagePlugins.php) | `الإعدادات والنظام` | سوق الإضافات | 2 |

---

### B. Address Resource Removal from Navigation

In [AddressResource.php](file:///e:/turbo_tenant/app/Plugins/LocationsDelivery/src/Filament/Resources/Addresses/AddressResource.php):
```php
// Hide from sidebar navigation; addresses belong strictly to orders and customer history
protected static bool $shouldRegisterNavigation = false;
```

---

### C. Slider Label Simplification

In [HeroSliderResource.php](file:///e:/turbo_tenant/app/Plugins/HeroBanners/src/Filament/Resources/HeroSliderResource.php):
```php
protected static ?string $navigationLabel = 'سلايدر';
protected static ?string $modelLabel = 'سلايدر';
protected static ?string $pluralModelLabel = 'سلايدر';
```

---

### D. Filament Enum Contracts Implementation

#### 1. Order Status (`App\Enums\OrderStatus`)
Update [OrderStatus.php](file:///e:/turbo_tenant/app/Enums/OrderStatus.php):
```php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING = 'pending';
    case PREPARING = 'processing';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::PREPARING => 'قيد التحضير',
            self::OUT_FOR_DELIVERY => 'خارج للتوصيل',
            self::DELIVERED => 'مكتمل (تم التوصيل)',
            self::CANCELLED => 'ملغي',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PREPARING => 'warning',
            self::OUT_FOR_DELIVERY => 'info',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PREPARING => 'heroicon-o-arrow-path',
            self::OUT_FOR_DELIVERY => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
```

#### 2. Create Order Type Enum (`App\Enums\OrderType`)
Create `app/Enums/OrderType.php`:
```php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel, HasColor, HasIcon
{
    case WEB_DELIVERY = 'web_delivery';
    case WEB_TAKEAWAY = 'web_takeaway';
    case POS = 'pos';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::WEB_DELIVERY => 'توصيل (ويب)',
            self::WEB_TAKEAWAY => 'استلام من الفرع',
            self::POS => 'نقطة بيع (POS)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::WEB_DELIVERY => 'success',
            self::WEB_TAKEAWAY => 'info',
            self::POS => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::WEB_DELIVERY => 'heroicon-o-truck',
            self::WEB_TAKEAWAY => 'heroicon-o-building-storefront',
            self::POS => 'heroicon-o-computer-desktop',
        };
    }
}
```

#### 3. Payment Status & Method Enums
Update [PaymentStatus.php](file:///e:/turbo_tenant/app/Enums/PaymentStatus.php) and [PaymentMethod.php](file:///e:/turbo_tenant/app/Enums/PaymentMethod.php) with `HasLabel` and `HasColor` returning clear Arabic labels and distinct colors.

---

## 3. Verification & Acceptance Criteria

- [ ] All 7 navigation groups appear in the exact configured order in the Filament sidebar.
- [ ] Addresses resource is completely hidden from the sidebar menu.
- [ ] Slider is labeled "سلايدر".
- [ ] `TextColumn::make('status')->badge()` and `TextColumn::make('type')->badge()` in [OrdersTable.php](file:///e:/turbo_tenant/app/Plugins/OrderManagement/src/Filament/Resources/Orders/Tables/OrdersTable.php) automatically render Arabic text, matching colors, and icons with zero custom closures.
