---
name: plugin-builder
description: Standalone plugin creation and scaffolding framework for Turbo Tenant Microkernel. Guides creating full-stack plugins across all 6 dimensions (logic, external integrations, tenant migrations, storage, Filament panels, and React slots).
allowed-tools: Read, Write, Edit, Glob, Grep
---

# Plugin Builder Skill

> Comprehensive framework and scaffolding engine for creating modular plugins within the **Turbo Tenant Microkernel Architecture**.

---

## 1. Architectural Philosophy

Every feature in Turbo Tenant is a self-contained plugin living in `app/Plugins/{StudlyName}/`.
A plugin is an atomic slice of functionality that can span up to **6 architectural dimensions**:

| Dimension | Implementation Standard |
| :--- | :--- |
| **1. Internal Logic** | Services in `src/Services/`, event listeners, pipeline pipes, and domain jobs. |
| **2. External Integrations** | API clients, webhook controllers registered via the plugin's ServiceProvider. |
| **3. Database Tables** | Migrations inside `database/migrations/tenant/`, executed strictly in tenant DB context. |
| **4. Storage Data** | Isolated directory in `storage/tenant{id}/plugins/{plugin-id}/`. |
| **5. Filament Panels** | Plugin class implementing `Filament\Contracts\Plugin` in `src/Filament/`. |
| **6. Frontend (React)** | Slot injection via `<PluginSlot />` or dedicated Inertia page components. |

---

## 2. Standard Plugin Directory Structure

```
app/Plugins/{StudlyName}/
├── plugin.json                                # Required: Manifest & dependencies
├── {StudlyName}ServiceProvider.php           # Required: Laravel ServiceProvider
├── {StudlyName}Plugin.php                    # Required: Tenant lifecycle hooks
├── database/
│   └── migrations/
│       └── tenant/                           # Optional: Tenant-isolated migrations
├── src/
│   ├── Services/                             # Domain business logic
│   ├── Models/                               # Eloquent models
│   ├── Listeners/                            # Event listeners
│   ├── Http/
│   │   └── Controllers/                      # Custom web/api/webhook controllers
│   └── Filament/
│       ├── {StudlyName}FilamentPlugin.php   # Implements Filament\Contracts\Plugin
│       ├── Resources/                        # Filament Resources
│       └── Pages/                            # Filament Settings / Custom Pages
└── resources/
    └── js/                                   # React components and slot widgets
```

---

## 3. Mandatory File Specifications

### 3.1 The Manifest (`plugin.json`)

Must exist in the plugin root:

```json
{
  "id": "sample-feature",
  "name": "Sample Feature Name",
  "version": "1.0.0",
  "category": "addon",
  "description": "Clear explanation of what this plugin provides.",
  "author": "Turbo Tenant Team",
  "is_core": false,
  "dependencies": [
    "catalog-products"
  ],
  "providers": [
    "App\\Plugins\\SampleFeature\\SampleFeatureServiceProvider"
  ],
  "filament": "App\\Plugins\\SampleFeature\\src\\Filament\\SampleFeatureFilamentPlugin"
}
```

#### Valid Categories:
`foundation`, `commerce`, `marketing`, `communication`, `storefront`, `addon`.

---

### 3.2 The Tenant Plugin Class (`{StudlyName}Plugin.php`)

Must extend `App\Core\Plugins\AbstractTenantPlugin`:

```php
<?php

declare(strict_types=1);

namespace App\Plugins\SampleFeature;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;

final class SampleFeaturePlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        // Boot runtime bindings, events, or pipeline pipes for this tenant
    }

    public function onActivate(Tenant $tenant): void
    {
        // Run seeders, default settings, or initial API handshakes
    }

    public function onDeactivate(Tenant $tenant): void
    {
        // Cleanup cache or notify external services
    }

    public function onUninstall(Tenant $tenant): void
    {
        // Pre-purge cleanup before migrations are rolled back
    }
}
```

---

### 3.3 The Service Provider (`{StudlyName}ServiceProvider.php`)

Registers dependency injection bindings, listeners, and routes:

```php
<?php

declare(strict_types=1);

namespace App\Plugins\SampleFeature;

use Illuminate\Support\ServiceProvider;

final class SampleFeatureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Container bindings
    }

    public function boot(): void
    {
        // Register routes, observers, or listeners
    }
}
```

---

### 3.4 The Filament Plugin (`{StudlyName}FilamentPlugin.php`)

If the plugin adds navigation, resources, or pages to the Admin Panel:

```php
<?php

declare(strict_types=1);

namespace App\Plugins\SampleFeature\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class SampleFeatureFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'sample-feature';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                // SampleResource::class,
            ])
            ->pages([
                // SampleSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
```

---

## 4. Scaffolding Automation

To instantly scaffold a complete, compliant plugin directory, run:

```bash
python .agent/skills/plugin-builder/scripts/scaffold_plugin.py <plugin-slug> [options]
```

### Options:
* `--category`: Category (`foundation`, `commerce`, `marketing`, `addon`, `storefront`). Default: `addon`.
* `--name`: Human-readable display name (e.g. `"Customer Loyalty"`).
* `--with-filament`: Scaffold Filament plugin class and resource folder.
* `--with-migrations`: Scaffold `database/migrations/tenant/` directory with a starter migration.
* `--with-frontend`: Scaffold `resources/js/` component slot stub.
* `--deps`: Comma-separated list of required plugin IDs (e.g. `--deps "catalog-products,order-management"`).

---

## 5. Plugin Creation Workflow (Step-by-Step)

When asked to create or extract a new plugin:

1. **Classify Dependencies:** Identify which foundation plugins this plugin requires.
2. **Scaffold Structure:** Run `scaffold_plugin.py` or create files following the template.
3. **Move / Implement Logic:**
   - Place models in `src/Models/` with correct namespace `App\Plugins\{Name}\src\Models`.
   - Place services in `src/Services/`.
   - Place tenant migrations in `database/migrations/tenant/`.
4. **Connect Hooks:**
   - If listening to events: add listener in ServiceProvider.
   - If intercepting pricing/validation: register pipe in ServiceProvider.
5. **Discover & Verify:**
   ```bash
   php artisan plugin:discover
   ```
6. **Test Tenant Activation:**
   ```bash
   php artisan tenant:plugin:activate <tenant_id> <plugin_id>
   ```

---

## 6. Strict Rules & Anti-Patterns

- ❌ **NEVER modify Core Kernel files** when creating a plugin. Core files must remain agnostic.
- ❌ **NEVER put migrations in central `database/migrations`** unless the plugin modifies central multi-tenant schemas.
- ❌ **NEVER hardcode Filament resources in `AdminPanelProvider.php`**. All resources must be registered through the plugin's `FilamentPlugin` class.
- ✅ **ALWAYS ensure tenant migrations are idempotent** using `Schema::hasTable()` guards where legacy tables might exist.
