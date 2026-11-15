# Store settings and branding

## Overview

Each store's identity, look, opening hours, currency, sign-in and email configuration, plus a settings page that other plugins extend with their own tabs (orders, payments, POS, Meta). Also the PWA manifest that lets customers install the storefront.

**Status:** live (core plugin, always active) · **Plugin:** `store-settings` ([plugin.json](../../app/Plugins/StoreSettings/plugin.json)) · **Related:** [Configuration](../configuration.md#store-settings-per-tenant), every plugin that contributes a tab

## How to use

### Store admin: settings

**الإعدادات والنظام › إعدادات المتجر** (Settings & system › Store settings), tabs:

- **المظهر والألوان** (Appearance): **السمة الجاهزة** (preset: default, terracotta, emerald, ocean, amber, burgundy) and **تخصيص الألوان والحواف** (custom colors and corner radius).
- **بطاقات الواجهة** (Storefront cards): product card options.
- **الهوية والمتجر** (Identity): names (EN/AR), description, **الشعارات والوسائط** (logo, SVG logo, favicon, image placeholder), contact email/phone/address, **روابط التواصل الاجتماعي** (Facebook, Instagram, X, custom links).
- **النظام وساعات العمل** (System & hours): **إعدادات النظام والتشغيل** (currency, maintenance switch), **أوقات وساعات العمل** (work hours per day with closed days and overnight ranges; accept orders outside hours), **الربط مع Google** (Google OAuth client id/secret/redirect), **إعدادات البريد الإلكتروني (SMTP)** (mailer, host, port, username, password, encryption, from address/name).
- Plugin tabs: orders, payments, POS, Meta (see those features).

Save; changes apply on the next request. The dashboard **setup checklist** widget tracks first steps (logo, first category, first product, first order) contributed by plugins.

### Store admin: installable app (PWA)

**الإعدادات والنظام › تطبيق الويب (PWA)**: full and short name, app id, theme and background colors, start URL, display mode, orientation. Saved to the store's `manifest.json`, served at `/manifest.json`.

### Customer

Sees the store's name, logo, colors, contact and social links; can install the storefront from the browser.

## How it works

1. **Storage:** [`SettingService`](../../app/Plugins/StoreSettings/src/Services/SettingService.php) reads/writes the tenant `settings` table ([`Setting`](../../app/Plugins/StoreSettings/src/Models/Setting.php)) with typed casting and a per-request in-memory cache. Keys and defaults: [`SettingKey`](../../app/Enums/SettingKey.php) plus each plugin's `*SettingKey` enum.
2. **Settings page:** [`Settings`](../../app/Plugins/StoreSettings/src/Filament/Pages/Settings.php) collects every [`SettingsSection`](../../app/Plugins/StoreSettings/src/Contracts/SettingsSection.php) tagged `store-settings.sections` from active plugins (own sections: [Appearance](../../app/Plugins/StoreSettings/src/Settings/StoreAppearanceSettingsSection.php), [Cards](../../app/Plugins/StoreSettings/src/Settings/StoreCardsSettingsSection.php), [Identity](../../app/Plugins/StoreSettings/src/Settings/StoreIdentitySettingsSection.php), [System](../../app/Plugins/StoreSettings/src/Settings/StoreSystemSettingsSection.php)).
3. **Storefront:** [`StoreSettingsBootstrapContributor`](../../app/Plugins/StoreSettings/src/Bootstrap/StoreSettingsBootstrapContributor.php) publishes public settings (names, logos as storage URLs, contact, social, cards, currency) in `/api/tenant/bootstrap`. Secrets are not included. The Blade shell inlines theme CSS variables built by [`ThemeCssBuilder`](../../app/Plugins/StoreSettings/src/Theme/ThemeCssBuilder.php) from the `theme` setting and [`ThemePresets`](../../app/Plugins/StoreSettings/src/Theme/ThemePresets.php) ([app.blade.php](../../resources/views/app.blade.php)).
4. **Mail:** [`ConfigureTenantMail`](../../app/Listeners/ConfigureTenantMail.php) applies SMTP settings on `TenancyInitialized`.
5. **PWA:** [`ManifestSettings`](../../app/Plugins/StoreSettings/src/Filament/Pages/ManifestSettings.php) and [`ManifestService`](../../app/Services/ManifestService.php) edit `storage/tenant<id>/app/manifest.json`, first created by [`TenantService::createManifestFile`](../../app/Services/TenantService.php).
6. **Admin brand:** the admin panel's brand name is the Arabic store name, falling back to the English one ([AdminPanelProvider](../../app/Providers/Filament/AdminPanelProvider.php)).

## Configuration

See [Configuration › Store settings](../configuration.md#store-settings-per-tenant). Notes:

- `maintenance_mode` is stored but not enforced by any code path; use `php artisan down` for installation-wide maintenance.
- Work hours are evaluated in UTC.
- Settings, including SMTP and gateway secrets, are stored as plain text.

## Testing

- Automated: [SettingsUxTest](../../tests/Feature/Filament/SettingsUxTest.php), [StoreSettingsCanonicalKeysTest](../../tests/Feature/Filament/StoreSettingsCanonicalKeysTest.php), [ThemeSettingsTest](../../tests/Feature/Filament/ThemeSettingsTest.php), [StoreSetupChecklistTest](../../tests/Feature/Filament/StoreSetupChecklistTest.php), [SettingServiceTest](../../tests/Feature/Services/SettingServiceTest.php), [BootstrapTest](../../tests/Feature/Api/BootstrapTest.php), [StoreHoursTest](../../tests/Unit/Services/Order/StoreHoursTest.php).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| A settings tab is missing | Its plugin is inactive | Activate the plugin |
| Logo not shown | File on tenant public disk not reachable | Check `storage:link` and the logo path |
| Colors unchanged | Browser cache of the shell | Hard refresh; the CSS is inlined per request |
| Maintenance switch has no effect | Not enforced | Use `php artisan down` |
| Password reset emails fail | Store SMTP settings wrong | Fix SMTP tab; check log |

## History

- Unreleased (working tree): settings tabs contributed by plugins, theme presets.
- 2026-10: setup checklist.
- 2026-02: social links management; Arabic store name.
- 2025-11: PWA manifest settings; Google settings per store.
