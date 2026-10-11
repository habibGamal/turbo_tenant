# Task: Microkernel Host & Plugin Engine Foundation (Milestone 0)

## Goal
Build the pure Microkernel Platform Host and stateful SaaS Plugin Lifecycle Engine for `turbo_tenant`, establishing central schemas, dependency resolution, per-tenant lifecycle operations, and runtime booting.

## Tasks
- [x] Task 1: Create Central Database Migrations (`plugins`, `tenant_plugins`) → Created `2026_10_10_000001_create_plugins_tables.php`.
- [x] Task 2: Create Core Plugin Contracts & Models (`TenantPluginInterface`, `Plugin`, `TenantPlugin`, Enums) → Implemented in `app/Core/Plugins/`.
- [x] Task 3: Build `PluginManifestLoader` and `PluginRegistry` to discover `app/Plugins/*/plugin.json` → Implemented.
- [x] Task 4: Build `PluginManager` with Dependency Resolution → Implemented in `PluginDependencyResolver.php` and `PluginManager.php`.
- [x] Task 5: Build `PluginLifecycleService` (Activate, Deactivate, Uninstall, Migrate) → Implemented.
- [x] Task 6: Hook `PluginManager` into `TenancyInitialized` in `TenancyServiceProvider` → Registered listener.
- [x] Task 7: Implement Artisan CLI commands (`plugin:discover`, `tenant:plugin:activate`, `tenant:plugin:deactivate`, `tenant:plugin:list`) → Registered in `PluginServiceProvider`.
- [ ] Task 8: Write Feature & Unit Tests in Pest → Test suite written in `tests/Feature/PluginEngineTest.php`; awaiting dependency verification.

## Done When
- [ ] Central DB holds `plugins` and `tenant_plugins` state.
- [ ] A sample plugin can be discovered, entitled, activated, migrated, and booted for a tenant with zero impact on other tenants.
- [ ] All tests pass without warnings.
