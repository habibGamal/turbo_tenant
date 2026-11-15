# Phase 3 (P1): Google Login, Especially the Native (Mobile) Callback

> **Host code:** `app/Http/Controllers/Auth/GoogleAuthController.php`, `routes/auth.php`
> **Covers:** I-5 `GET /auth/google/native-callback`, I-6 `GET /auth/google/callback`

---

## 1. Findings

### 1.1 HIGH: the native callback accepts a Google access token issued to any app
- `handleNativeCallback` (`GoogleAuthController.php:92`) calls `Socialite::driver('google')->userFromToken($accessToken)`. This only asks Google "whose token is this?". It does **not** check that the token was issued to **our** OAuth client.
- **Exploit (token substitution):** an attacker runs any app with "Sign in with Google". A victim signs into it, and the attacker now holds a valid access token for the victim. The attacker calls `/auth/google/native-callback?access_token=<that token>` on a tenant domain and is logged in **as the victim**. If no account exists, one is created for the victim's email and marked verified.

### 1.2 MEDIUM: the access token travels in the URL, and login state changes on GET
- The route is GET with `access_token` in the query string, so the token ends up in web server access logs, proxy logs and browser history.
- GET login also allows login CSRF (forcing a victim into the attacker's account).

### 1.3 MEDIUM: account linking by email trusts Google's email blindly
- Both callbacks link `google_id` to any existing user with the same email, and new users get `email_verified_at = now()`, without checking Google's `email_verified` claim. Workspace or unverified addresses could take over an existing password account.

### 1.4 LOW: `remember=true` on every Google login
- `Auth::login($user, true)` gives every Google session a long-lived remember cookie by default.

---

## 2. Target design

- The mobile app sends a **Google ID token** (a JWT) via `POST /api/tenant/auth/google/native` with JSON body `{ id_token, expo_token? }`.
- The server verifies it with `google/apiclient` (`Google\Client::verifyIdToken`) or JWKS:
  - `aud` is in the allowed client ids (web, iOS and Android client ids from config),
  - `iss` is `accounts.google.com` or `https://accounts.google.com`,
  - `exp` has not passed,
  - `email_verified === true`.
- Accounts are linked only when `email_verified` is true. An existing user **with a password and no `google_id`** must confirm the link (log in with their password once) or receive a link email.

---

## 3. Tasks

- [ ] **T3.1** Add `POST /api/tenant/auth/google/native` (throttle `10,1`) that verifies the ID token as in §2. Return the same JSON shape as `AuthController::login`.
- [ ] **T3.2** Keep the old GET route for one mobile release, but **add an audience check**: call `https://oauth2.googleapis.com/tokeninfo?access_token=…` and require `aud`/`azp` to be in the allowed client ids. Log a deprecation warning, then remove the route.
- [ ] **T3.3** Require `email_verified` before linking or creating an account. For an existing password user with no `google_id`, refuse automatic linking and return `409 link_required`.
- [ ] **T3.4** Use `Auth::login($user, remember: $request->boolean('remember'))`.
- [ ] **T3.5** Tests: a token whose `aud` is a foreign client → 401; `email_verified=false` → 401; existing password user → 409; happy path → 200 with a session.
- [ ] **T3.6** Config: `services.google.allowed_client_ids` (comma-separated env var). Document it in `GOOGLE_OAUTH_GUIDE.md`.

## 4. Coordination

The mobile app has to switch from sending `access_token` to `id_token` (Expo AuthSession / `@react-native-google-signin` both expose `idToken`). Ship T3.2 first, because it is safe without an app change, then T3.1, and remove the GET route after the mobile release ships.
