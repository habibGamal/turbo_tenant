# Scope Filament panel plugins per active tenant

## What
Load only active plugins' Filament panels per tenant in `AdminPanelProvider` instead of registering every discovered plugin globally, and review enabling `VerifyCsrfToken` and MFA on the tenant admin panel.

## Why
`AdminPanelProvider::panel()` currently loops `$registry->all()` rather than `PluginManager::getActiveFilamentPluginsForTenant()`, so resources/widgets for plugins a tenant has deactivated still register on the panel. `VerifyCsrfToken` and `multiFactorAuthentication` are also commented out in `AdminPanelProvider`.

## Where in code
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Core/Plugins/Services/PluginManager.php`

## Done when
- Deactivated plugins do not expose Filament resources, pages, or widgets to that tenant's `/admin` panel.
- Decision on `VerifyCsrfToken` (cross-subdomain Livewire uploads) and panel MFA is recorded and implemented.
- Filament panel isolation tests verify inactive plugins stay hidden.
