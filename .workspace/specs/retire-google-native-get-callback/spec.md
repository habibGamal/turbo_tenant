# Retire deprecated Google native GET callback

## What
Remove `GET /auth/google/native-callback` (`GoogleAuthController::handleNativeCallback`) once mobile clients exclusively use `POST /api/tenant/auth/google/native` with Google ID tokens.

## Why
`GET /auth/google/native-callback` passes `access_token` in the query string (exposed in access logs and browser history) and performs login state changes on GET. It was kept temporarily with an audience check and deprecation log warning until mobile releases finish migrating.

## Where in code
- `routes/auth.php`
- `app/Http/Controllers/Auth/GoogleAuthController.php`

## Done when
- Mobile clients use `POST /api/tenant/auth/google/native` with `id_token`.
- The GET route and `handleNativeCallback` method are removed and tests pass.
