# Customer profile phone persistence

## What
Decide whether `users.phone` belongs on registered customer accounts and, if yes, add the column and persist `phone` in `ProfileUpdateRequest` and `AuthController::updateProfile`.

## Why
The storefront profile screen submits a phone number, and `User::customerPhone()` reads `$user->phone`, but the `users` table has no `phone` column and `AuthController::updateProfile` only saves `name` and `email`. `tests/e2e/plugins/auth/profile.live.spec.ts` currently marks `profile phone persists after saving` as expected-fail.

## Where in code
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Requests/ProfileUpdateRequest.php`
- `app/Models/User.php`
- `database/migrations/tenant/`
- `tests/e2e/plugins/auth/profile.live.spec.ts`

## Done when
- Either `users.phone` is added, persisted, returned by `CustomerResource`, and the E2E expected-fail is removed, or the unused phone field is removed from the profile UI.
