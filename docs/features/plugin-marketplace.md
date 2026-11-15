# Plugin marketplace

## Overview

Store admins switch capabilities on and off for their store. Each capability is a plugin; activation checks dependencies, runs the plugin's database migrations and enables its routes, admin screens, settings tabs and storefront sections.

**Status:** live (host feature) · **Code:** [ManagePlugins](../../app/Filament/Pages/ManagePlugins.php), [PluginLifecycleService](../../app/Core/Plugins/Services/PluginLifecycleService.php) · **Related:** [Architecture](../architecture.md), [Store provisioning](tenant-provisioning.md)

## How to use

### Store admin

1. Open **الإعدادات والنظام › سوق الإضافات** (Settings & system › Plugin marketplace, `/admin/plugins`).
2. Filter by category or status. Each card shows the plugin, its status and dependencies.
3. **Install/activate:** if dependencies are missing, a dialog lists them and installs them first, then the plugin.
4. **Deactivate:** refused for core plugins and for plugins another active plugin depends on. Data and files are kept.
5. **Uninstall:** after confirmation, removes the store's plugin record and runs the plugin's uninstall hook.

### Operator (CLI)

```bash
php artisan tenant:plugin:list acme
php artisan tenant:plugin:activate acme coupons-promotions
php artisan tenant:plugin:deactivate acme coupons-promotions
```

## How it works

1. **Catalog:** `plugin.json` files are read by [`PluginManifestLoader`](../../app/Core/Plugins/Services/PluginManifestLoader.php), cached in `bootstrap/cache/plugins.php` and mirrored to the central `plugins` table by `plugin:discover` ([`PluginRegistry`](../../app/Core/Plugins/Services/PluginRegistry.php)).
2. **Activate** ([`PluginLifecycleService::activate`](../../app/Core/Plugins/Services/PluginLifecycleService.php)): no-op if already active → [`PluginDependencyResolver`](../../app/Core/Plugins/Services/PluginDependencyResolver.php) checks missing active dependencies → runs `migrate --path=<plugin>/database/migrations/tenant` inside the tenant → creates `storage/.../plugins/<id>` → upserts `tenant_plugins` as `active` → resets cached plugin state → calls the plugin's `onActivate`. If `onActivate` throws, the record is restored to its previous state; migrations already applied stay.
3. **Deactivate:** refuses core and depended-on plugins, sets `deactivated`, calls `onDeactivate`.
4. **Uninstall:** refuses core plugins, calls `onUninstall` inside the tenant, deletes the `tenant_plugins` row.
5. **Runtime effect:** see [Architecture › Request lifecycle](../architecture.md#request-lifecycle): routes 404 when inactive, contributions and listeners skipped, frontend routes hidden.

## Configuration

`plugin.json` per plugin (`id`, `name`, `category`, `is_core`, `dependencies`, `providers`, `plugin`, `filament`, `listeners`, `tenant_scoped`). Categories: foundation, commerce, marketing, communication, storefront, addon ([`PluginCategory`](../../app/Core/Plugins/Enums/PluginCategory.php)).

[`GrandfatherExistingTenantsPluginsSeeder`](../../database/seeders/GrandfatherExistingTenantsPluginsSeeder.php) marks every plugin active for every existing tenant (used once when the plugin system was introduced). It writes records only and does not run plugin migrations.

## Testing

- Automated: [PluginLifecycleTest](../../tests/Feature/PluginLifecycleTest.php), [PluginEngineTest](../../tests/Feature/PluginEngineTest.php), [EnsurePluginActiveTest](../../tests/Feature/EnsurePluginActiveTest.php), [DynamicPluginRelationsTest](../../tests/Feature/DynamicPluginRelationsTest.php), [TenantsPluginMigrateCommandTest](../../tests/Feature/TenantsPluginMigrateCommandTest.php), [tests/Unit/Core](../../tests/Unit/Core), [PluginBoundariesTest](../../tests/Feature/Security/PluginBoundariesTest.php).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| "Missing active dependencies" | Activation order | Accept the dependency dialog, or activate dependencies first |
| "mandatory core plugin" | Tried to deactivate store-settings/media-optimizer | Not allowed |
| "Plugin 'x' depends on it" | Deactivating a dependency | Deactivate the dependent first |
| Activation failed on a migration | Migration error inside the tenant | Fix and retry; earlier migrations remain applied |
| New plugin not listed | Stale manifest cache | `php artisan plugin:discover` |

## History

- 2026-10: plugin microkernel, marketplace page, tenant-isolated plugin boot, activation-gated listeners.
