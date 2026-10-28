# Plan: Filament Plugins Management Page

## Goal
Implement a responsive Marketplace / App Store-style Filament page in the Tenant Admin Panel (`/admin`) allowing store administrators to discover, install, activate, deactivate, and uninstall modular plugins with dependency resolution and visual status badges.

## Tasks
- [x] Task 1: Create `App\Filament\Pages\ManagePlugins` page class with Livewire actions (`installPlugin`, `deactivatePlugin`, `uninstallPlugin`), category filtering, search, and dependency validation logic → Verify: File exists and passes PHP syntax check.
- [x] Task 2: Create Blade view `resources/views/filament/pages/manage-plugins.blade.php` with responsive cards, category badges, dependency status pills, search & filter bars, and action buttons → Verify: Blade template renders cleanly without syntax errors.
- [x] Task 3: Register `ManagePlugins::class` in `App\Providers\Filament\AdminPanelProvider` → Verify: Provider registers page in the tenant admin panel navigation.
- [ ] Task 4: Write comprehensive Pest/PHPUnit feature tests (scheduled for later as requested by user).
- [x] Task 5: Run linting and validation scripts → Verify: Pint and view cache pass with 0 errors.

## Done When
- [x] Tenant admins can access `/admin/plugins` (navigation item "سوق الإضافات").
- [x] Available plugins from `PluginRegistry` are rendered in a clean grid with category badges and dependency badges.
- [x] Admins can install/activate plugins with prerequisite dependency handling.
- [x] Admins can deactivate and safely uninstall non-core plugins with confirmation.
- [x] Core plugins are protected from deactivation/uninstallation.
- [ ] Feature tests pass (to be run later).

## Notes
- Tenant context is managed by Stancl Tenancy (`tenant()`).
- All lifecycle operations use `App\Core\Plugins\Services\PluginLifecycleService`.
- Core plugins (`is_core: true`) cannot be deactivated or uninstalled.
