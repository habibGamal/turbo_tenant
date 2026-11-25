# Customer accounts

## Overview

Customers register and sign in per store with email/password or Google, reset their password by email, and manage their profile and password. Accounts are stored in the store's own database, so each store has separate customers. Store admins are customers with `is_admin` set.

**Status:** live (host feature, always available) · **Code:** [routes/tenant.php](../../../routes/tenant.php), [routes/auth.php](../../../routes/auth.php), [app/Http/Controllers](../../../app/Http/Controllers) · **Related:** [Google login](../integrations/google-login.md), [Cart](cart.md) (cart merge on login), [Push notifications](push-notifications.md) (device token), [Locations](locations-delivery.md) (address book)

## How to use

### Customer

1. **Register** (`/register`): name, email, password + confirmation.
2. **Sign In** (`/login`): email and password, or **Sign in with Google**. After sign-in you return to the page that sent you (e.g. `/login?redirect=/checkout`).
3. **Forgot password** (`/forgot-password`): enter your email; open the emailed link (`/reset-password/:token`) and set a new password.
4. **Profile** (`/profile`): change name/email, change password, manage addresses, sign out.

### Store admin: manage customers and admins

**العملاء والتقييمات › المستخدمين** (Customers & reviews › Users) lists users with extra columns from plugins (e.g. order count). Admin access to `/admin` requires `is_admin`; Filament supports app-based MFA for panel users.

### Rules customers notice

- Password reset, password change: limited to 6 attempts per minute. Google native sign-in: 10 per minute.
- The phone field on the profile screen is not saved (known gap covered by an expected-failure E2E test).

## How it works

1. **Session login:** [`AuthController::login`](../../../app/Http/Controllers/Api/AuthController.php) uses [`LoginRequest::authenticate`](../../../app/Http/Requests/Auth/LoginRequest.php) (with its own throttling) and regenerates the session; optional `expo_token` is saved. `register` validates and logs the user in. `logout` invalidates the session. `user` returns the current user via `CustomerResource`.
2. **Google (web):** `/auth/google` → [`GoogleAuthController`](../../../app/Http/Controllers/Auth/GoogleAuthController.php) redirects with Socialite configured from the store's Google settings; the callback finds or creates the user ([`GoogleAccountService`](../../../app/Services/Auth)) and signs in.
3. **Google (native app):** `POST /api/tenant/auth/google/native` with an `id_token` → [`GoogleNativeAuthController`](../../../app/Http/Controllers/Api/GoogleNativeAuthController.php) verifies the token signature, audience (`GOOGLE_ALLOWED_CLIENT_IDS`) and claims with [`GoogleIdTokenVerifierService`](../../../app/Services/Auth/GoogleIdTokenVerifierService.php) before signing in.
4. **Cart merge:** the `Login` event moves the visitor cart into the account ([Cart](cart.md#how-it-works)).
5. **Logging:** sensitive fields are redacted from log context ([RedactSensitiveContextProcessorTest](../../../tests/Unit/Logging/RedactSensitiveContextProcessorTest.php)); passwords are not logged.
6. **Admin access:** [`User::canAccessPanel`](../../../app/Models/User.php#L52) returns `is_admin` for the `admin` panel and `true` for other panels.

Data: tenant `users` (`name`, `email`, `password`, `google_id`, `avatar`, `email_verified_at`, `is_admin`, `expo_token`), `password_reset_tokens`, `sessions`.

## Configuration

| Setting | Where | Effect |
| --- | --- | --- |
| Google client id/secret/redirect | Store settings › الربط مع Google | Web Google sign-in for that store |
| `GOOGLE_ALLOWED_CLIENT_IDS` | `.env` | Accepted audiences for native ID tokens |
| SMTP | Store settings | Reset emails |
| `SESSION_LIFETIME` | `.env` | Session length |

## Testing

- Automated: [AuthApiTest](../../../tests/Feature/Api/AuthApiTest.php), [tests/Feature/Auth](../../../tests/Feature/Auth), [GoogleNativeLoginSecurityTest](../../../tests/Feature/Security/GoogleNativeLoginSecurityTest.php), [LoginLoggingTest](../../../tests/Feature/Security/LoginLoggingTest.php), [ProfileTest](../../../tests/Feature/ProfileTest.php), [UserResourceTest](../../../tests/Feature/Filament/Resources/UserResourceTest.php).
- Browser: [tests/e2e/plugins/auth](../../../tests/e2e/plugins/auth) via `npm run test:e2e:auth` (rules in [Development guide](../development.md#live-account-browser-tests)).

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Google button fails | Store Google settings missing or redirect URL not registered in Google Console | Set them; register `https://<store>/auth/google/callback` |
| Native Google login 401/422 | Client id not in `GOOGLE_ALLOWED_CLIENT_IDS` | Add the mobile client id |
| Reset email not received | Store SMTP wrong, or mailer `log` | Fix SMTP |
| Signed in on one store but not another | Accounts are per store | Expected |
| Cannot open `/admin` | `is_admin` false | Promote the user |

## History

- 2026-10: Google tokens verified, passwords no longer logged, auth decoupled from the cart plugin; Inertia removed in favor of the SPA.
- 2025-12: Google OAuth for web and native, Filament MFA.
- 2025-11: registration and password reset pages.
