# Plan: Filament Plugins Management Page

## Goal
Implement a native Filament v4 Marketplace page in the Tenant Admin Panel (`/admin/plugins`) allowing store administrators to discover, filter, search, sort, install, activate, deactivate, and uninstall modular plugins with dependency resolution and live overview stats.

## Tasks
- [x] Task 1: Create `App\Filament\Widgets\PluginStatsOverview` header `StatsOverviewWidget` (`$isDiscovered = false`) showing total, active, available, and core plugin counts and listening to `plugins-updated` → Verify: Widget renders 4 stats cards without appearing on the main Dashboard.
- [x] Task 2: Refactor `App\Filament\Pages\ManagePlugins` to use native Filament v4 `HasTable` / `InteractsWithTable` (`$table->records(...)` with `LengthAwarePaginator`), `HasTabs` status tabs above `EmbeddedTable`, category `SelectFilter`, search, sorting, and per-row lifecycle `Action` buttons with conditional confirmation modals (removing custom Blade view `manage-plugins.blade.php`) → Verify: Page renders cleanly using `filament-panels::pages.page`.
- [x] Task 3: Register `ManagePlugins::class` in `App\Providers\Filament\AdminPanelProvider` → Verify: Provider registers page in the tenant admin panel navigation.
- [x] Task 4: Write comprehensive feature tests in `tests/Feature/Filament/ManagePluginsTest.php` covering stats widget, tabs, search, category filter, dependency-ordered install modal, and core/dependent protection on deactivate/uninstall → Verify: Feature tests pass.
- [x] Task 5: Run Pint, PHPStan, view cache clear, and `docs_check.py` → Verify: Zero lint, static analysis, test, or handbook errors.

## Done When
- [x] Tenant admins can access `/admin/plugins` (navigation item "سوق الإضافات").
- [x] Available plugins from `PluginRegistry` are rendered in a native Filament Table with category, version, dependency, and status badges.
- [x] Admins can install/activate plugins with automatic topological prerequisite dependency handling.
- [x] Admins can deactivate and safely uninstall non-core plugins with confirmation.
- [x] Core plugins and plugins with active dependents are protected from deactivation/uninstallation.
- [x] `ManagePluginsTest` and `SettingsUxTest` pass.

## Notes
- Tenant context is managed by Stancl Tenancy (`tenant()`).
- All lifecycle operations use `App\Core\Plugins\Services\PluginLifecycleService`.
- Core plugins (`is_core: true`) cannot be deactivated or uninstalled.
