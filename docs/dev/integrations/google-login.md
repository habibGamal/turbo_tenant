# Google login

Customers can sign in or register with Google from the storefront web flow or with a Google ID token from a native mobile client.

**Related:** [Customer accounts](../features/customer-accounts.md), [Configuration](../configuration.md)

## Flows

### Web OAuth redirect

1. `GET /auth/google` ([`GoogleAuthController::redirect`](../../../app/Http/Controllers/Auth/GoogleAuthController.php)) applies the store's Google OAuth settings via [`GoogleOAuthConfigService`](../../../app/Services/Auth/GoogleOAuthConfigService.php) and redirects to Google using Laravel Socialite. Optional `expo_token` and `remember` query parameters are saved in session.
2. `GET /auth/google/callback` ([`GoogleAuthController::callback`](../../../app/Http/Controllers/Auth/GoogleAuthController.php)) checks that Google's `email_verified` claim is true, resolves or creates the user via [`GoogleAccountService`](../../../app/Services/Auth/GoogleAccountService.php), logs the customer in with session regeneration, and registers `expo_token` if provided.

### Native mobile ID token

`POST /api/tenant/auth/google/native` (rate-limited to `10/min`, [`GoogleNativeAuthController::login`](../../../app/Http/Controllers/Api/GoogleNativeAuthController.php)) accepts `{ id_token, expo_token?, remember? }`:

1. [`GoogleIdTokenVerifierService::verifyIdToken`](../../../app/Services/Auth/GoogleIdTokenVerifierService.php) verifies the JWT signature and checks `aud`/`azp` against `GOOGLE_ALLOWED_CLIENT_IDS` (falling back to `GOOGLE_CLIENT_ID`).
2. `email_verified` must be true, otherwise `401` is returned.
3. [`GoogleAccountService::resolveUser`](../../../app/Services/Auth/GoogleAccountService.php) links or creates the account. If an existing account has a password and no `google_id`, it returns `409` with `code: "link_required"` instead of auto-linking.
4. On success, starts a stateful session, regenerates the session ID, registers optional `expo_token`, and returns `CustomerResource`.

The legacy `GET /auth/google/native-callback?access_token=...` route in [routes/auth.php](../../../routes/auth.php) is deprecated; it checks token audience via `tokeninfo` and logs a deprecation warning.

## Configuration

### Per-store settings (web OAuth)

Configured in **إعدادات المتجر › النظام وساعات العمل › الربط مع Google** ([`StoreSystemSettingsSection`](../../../app/Plugins/StoreSettings/src/Settings/StoreSystemSettingsSection.php)):

| Key | Purpose |
| --- | --- |
| `google_client_id` | OAuth 2.0 Web client ID |
| `google_client_secret` | OAuth 2.0 Web client secret |
| `google_redirect_url` | Authorized callback URL (`https://<store-domain>/auth/google/callback`) |

Store settings override the `.env` values (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL`).

### Environment variables (native ID tokens)

- `GOOGLE_ALLOWED_CLIENT_IDS`: comma-separated OAuth client IDs (web, iOS, Android) accepted when verifying ID tokens. Falls back to `GOOGLE_CLIENT_ID` when unset.

## Data

Tenant `users` columns used: `google_id`, `avatar`, `email_verified_at`, `expo_token`. New Google users receive a random generated password.

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| "Unable to login with Google" | Missing client ID/secret, redirect URI mismatch, or unverified email | Verify credentials in Store Settings and add `https://<store>/auth/google/callback` in Google Cloud Console |
| Native login returns `401` | Token expired or `aud` not in `GOOGLE_ALLOWED_CLIENT_IDS` | Add the mobile client ID to `GOOGLE_ALLOWED_CLIENT_IDS` |
| Native login returns `409 link_required` | Account already exists with a password and no `google_id` | Sign in with email and password |
