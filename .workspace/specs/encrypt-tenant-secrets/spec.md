# Encrypt tenant secrets at rest

## What
Encrypt all third-party secret keys in the tenant `settings` table (`Crypt::encryptString` on write, `Crypt::decryptString` on read) and stop Filament settings fields from echoing stored secret values back to the browser.

## Why
Currently only `meta_capi_access_token` (`MetaSettingsSection`) and `branches.pos_api_key` (`Branch` model cast) are encrypted at rest. Payment gateway keys (`paymob_secret_key`, `paymob_hmac_secret`, `kashier_api_key`, `kashier_secret_key`), `google_client_secret`, `mail_password`, `products_repo_api_key`, and the POS `integration_token` are stored as plain text in `settings` and use `->revealable()` in Filament.

## Where in code
- `app/Core/Contracts/SettingDefinition.php`
- `app/Plugins/StoreSettings/src/Services/SettingService.php`
- `app/Enums/SettingKey.php`
- `app/Plugins/PaymentProcessing/src/Settings/PaymentSettingKey.php`
- `app/Plugins/PaymentProcessing/src/Settings/PaymentSettingsSection.php`
- `app/Plugins/StoreSettings/src/Settings/StoreSystemSettingsSection.php`
- `app/Plugins/PosIntegration/src/Settings/PosSettingKey.php`
- `app/Plugins/OrderManagement/src/Services/IntegrationTokenService.php`

## Done when
- Secret keys are encrypted at rest on write and decrypted on read, with an additive tenant migration encrypting existing plain-text values.
- Filament settings forms display password inputs without `->revealable()` and keep the existing stored secret when submitted blank.
- Feature tests verify encryption at rest and non-echoing Filament form behavior.
