# Phase 6 Migration Plan: Microkernel Decoupling & Tenant Grandfathering

> **Milestone:** M6  
> **Scope:** Monolithic route stripping, Filament dynamic panel cutover, production tenant grandfathering seeder, and regression verification  
> **Target Path:** `.plan/migration_to_plugins/06_phase6_microkernel_zero_coupling_and_grandfathering.md`  

---

## 1. Objectives & Overview

Phase 6 executes the final architectural cutover:
1. Strips all domain resource discoveries from `AdminPanelProvider.php`.
2. Strips all business routes from `routes/tenant.php`.
3. Runs the **Tenant Grandfathering Seeder** to ensure every existing active tenant in production is entitled to all 15 plugins with `active` status.
4. Executes full end-to-end regression and smoke tests.

---

## 2. Decoupling Filament: `AdminPanelProvider.php`

All domain resources are unregistered from global discovery. Instead, `AdminPanelProvider` dynamically inquires `PluginManager` for the active tenant's Filament plugins:

```php
public function panel(Panel $panel): Panel
{
    $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login(Login::class)
        ->sidebarCollapsibleOnDesktop()
        ->colors([
            'primary' => Color::Blue,
        ])
        ->pages([
            Dashboard::class,
        ])
        ->widgets([
            AccountWidget::class,
        ]);

    // Attach active tenant plugins dynamically:
    if (function_exists('tenant') && tenant()) {
        $activeFilamentPlugins = app(\App\Core\Plugins\Services\PluginManager::class)
            ->getActiveFilamentPluginsForTenant(tenant());

        foreach ($activeFilamentPlugins as $filamentPlugin) {
            $panel->plugin($filamentPlugin);
        }
    }

    return $panel;
}
```

---

## 3. Decoupling Routes: `routes/tenant.php`

All 15 plugin domains manage their routes within their respective Service Providers via `$this->loadRoutesFrom(...)`.

### What Remains in `routes/tenant.php` (Platform Host Shell Only):
```php
<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Tenant File Server & Dynamic Theme Assets
Route::get('/storage/{path}', [App\Http\Controllers\TenantStorageController::class, 'serve'])
    ->where('path', '.*')
    ->middleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class]);

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/manifest.json', [App\Http\Controllers\TenantManifestController::class, 'show']);
    Route::get('/theme.css', [App\Http\Controllers\TenantThemeController::class, 'show']);

    // Core Customer / Base User Profile
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // Base Authentication Routes
    require __DIR__.'/auth.php';
});
```

---

## 4. Production Tenant Grandfathering Seeder

To honor the user approval for grandfathering existing tenants with zero downtime or feature interruption, a dedicated database seeder (`GrandfatherExistingTenantsPluginsSeeder`) is created:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Illuminate\Database\Seeder;

final class GrandfatherExistingTenantsPluginsSeeder extends Seeder
{
    public function run(): void
    {
        $registry = app(PluginRegistry::class);
        $registry->discover();
        $registry->syncDatabase();

        $allPluginIds = array_keys($registry->all());
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            foreach ($allPluginIds as $pluginId) {
                TenantPlugin::firstOrCreate(
                    [
                        'tenant_id' => (string) $tenant->id,
                        'plugin_id' => $pluginId,
                    ],
                    [
                        'status' => PluginStatus::ACTIVE,
                        'settings' => [],
                        'activated_at' => now(),
                        'installed_at' => now(),
                    ]
                );
            }
        }
    }
}
```

---

## 5. End-to-End Verification Matrix

| Verification Step | Command / Action | Expected Result |
| :--- | :--- | :--- |
| **1. Central Plugin Catalog** | `php artisan plugin:discover` | 15 plugins synced into `plugins` table. |
| **2. Grandfathering Run** | `php artisan db:seed --class=GrandfatherExistingTenantsPluginsSeeder` | Existing tenants gain `active` status for all 15 plugins in `tenant_plugins`. |
| **3. Guest Checkout Disabled** | `php artisan tenant:plugin:deactivate {tenant} guest-checkout` | Direct visit to `/checkout` redirects to `/login`. Direct API order submission returns HTTP 422. |
| **4. Core Settings Protection** | `php artisan tenant:plugin:deactivate {tenant} store-settings` | Throws `RuntimeException: Plugin 'store-settings' is a mandatory core plugin`. |
| **5. Full Storefront Flow** | Add to Cart $\to$ Apply Coupon $\to$ Select Branch $\to$ Pay with Paymob $\to$ Order Success | Order processes end-to-end across decoupled plugins. |
| **6. Automated Pest Suite** | `php artisan test` | Entire suite passes green with 100% assertions satisfied. |
