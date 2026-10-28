# Migration Plan: GuestCheckout Inertia-to-API Decoupling

## Overview
Migrate `GuestCheckout` plugin from legacy Inertia.js tight-coupling to decoupled REST API + Headless TanStack Query + Multi-Theme Overrides architecture with multi-slot support (`order.tracking` and `checkout.guest.form`).

---

## Tasks

### Phase 1: Scaffolding
- [x] Run `python .agent/skills/plugin-inertia-to-api/scripts/migrate_plugin.py GuestCheckout` to scaffold directories and boilerplate.

### Phase 2: Backend REST API
- [x] Implement `GuestCheckoutApiController` in `app/Plugins/GuestCheckout/src/Http/Controllers/Api/GuestCheckoutApiController.php` with:
  - `track(Request $request): JsonResponse` supporting `order_number`, `phone`, and `phone_country_code` (defaulting to `+20`).
  - Eager-loading order relationships (`items.extras`, `items.product.weightOption`, `items.weightOptionValue`, `branch`, `guestUser`).
- [x] Update `app/Plugins/GuestCheckout/routes/tenant.php` to register `/api/tenant/guest-checkout/track` (and `/orders/track` alias for backwards compatibility) returning JSON.
- [x] Retain web route `/track-order` so inertia/browser access to the track page continues to function.

### Phase 3: Frontend Domain Module (`resources/js/plugins/guest-checkout/`)
- [x] Define TypeScript types in `resources/js/plugins/guest-checkout/types/index.ts`.
- [x] Implement TanStack Query hook `useGuestOrderTracking` in `resources/js/plugins/guest-checkout/api/useGuestOrderTracking.ts`.
- [x] Implement headless fallback UI in `resources/js/plugins/guest-checkout/components/DefaultGuestOrderTracking.tsx` and `DefaultGuestCheckout.tsx`.
- [x] Register default slots (`order.tracking`, `checkout.guest.form`) in `resources/js/plugins/guest-checkout/index.ts`.

### Phase 4: Theme Override & Page Decoupling
- [x] Implement themed component in `resources/js/themes/default/plugins/guest-checkout/ThemedGuestOrderTracking.tsx`.
- [x] Register theme override for `default` theme in `resources/js/themes/default/plugins/guest-checkout/index.ts`.
- [x] Refactor `resources/js/themes/default/pages/Track.tsx` to consume the headless tracking hook instead of raw axios calls.

### Phase 5: Verification & Testing
- [x] Run PHP tests: `php artisan test --filter=GuestOrderTrackingTest` (13/13 passed).
- [x] Run full guest suite: `php artisan test --filter=GuestOrder` (33/33 passed).
- [x] Run TypeScript check: `npx tsc --noEmit` (0 errors).
- [x] Run asset build: `npm run build` (built in 8.51s, Track page chunk reduced from ~33KB to 0.75KB).
