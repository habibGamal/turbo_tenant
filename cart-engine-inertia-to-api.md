# CartEngine: Inertia to API & Headless React Migration

## Goal
Migrate the `CartEngine` plugin from legacy Inertia.js tight-coupling to decoupled REST API (`/api/cart/*`), Headless React TanStack Query hooks with optimistic updates and snapshot rollbacks, Default Fallback UI, and Theme Slot Overrides (`cart.page`, `header.actions.cart`).

## Tasks
- [x] Task 1: Run automated scaffolding script `python .agent/skills/plugin-inertia-to-api/scripts/migrate_plugin.py CartEngine` → Verify: files generated in `app/Plugins/CartEngine/src/Http/Controllers/Api/` and `resources/js/plugins/cart-engine/`
- [x] Task 2: Implement REST API endpoints in `CartEngineApiController.php` (index, store, update, destroy, clear, sync) → Verify: PHP syntax valid and returns `JsonResponse`
- [x] Task 3: Configure routes in `app/Plugins/CartEngine/routes/tenant.php` under `/api/cart/*` and remove Inertia renders → Verify: `php artisan route:list` shows clean API routes
- [x] Task 4: Define TypeScript interfaces in `resources/js/plugins/cart-engine/types/index.ts` → Verify: types cover Cart, CartItem, CartItemExtra, AddToCartPayload, UpdateCartItemPayload, and API responses
- [x] Task 5: Implement TanStack Query v5 headless hooks in `resources/js/plugins/cart-engine/api/` (`useCart`, `useAddToCart`, `useUpdateCartItem`, `useRemoveCartItem`, `useClearCart`, `useSyncCart`) with optimistic snapshot rollbacks → Verify: hooks use `@/core/api/client` and TypeScript compiles
- [x] Task 6: Implement fallback components & slot registration in `resources/js/plugins/cart-engine/` (`DefaultCart`, `DefaultCartButton`, `index.ts`) → Verify: `registerCartEnginePlugin` registers defaults to slot registry
- [x] Task 7: Implement Theme Overrides in `resources/js/themes/default/plugins/cart-engine/` (`ThemedCart`, `ThemedCartButton`, `index.ts`) → Verify: registers overrides to 'default' theme
- [x] Task 8: Wire up plugin bootstrap in `resources/js/plugins/registry.ts`, update `AppRouter.tsx` (`/cart` route to render `PluginSlot name="cart.page"`), and refactor `Navigation.tsx` / `cartUtils.ts` → Verify: storefront consumes slot and API client cleanly
- [x] Task 9: Verify TypeScript build and validate runtime integrity → Verify: `npx tsc --noEmit` passes with 0 errors

## Done When
- [x] Backend controllers return pure `JsonResponse` under `/api/cart/*`.
- [x] No `Inertia::render` remains in `CartEngine` controllers.
- [x] Headless hooks cleanly fetch data using TanStack Query v5 with optimistic snapshot rollback.
- [x] Slot registry enables both default fallbacks and default theme overrides for cart page and header button.
- [x] TypeScript check (`npx tsc --noEmit`) passes cleanly.
