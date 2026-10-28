# CustomerFavorites Inertia-to-API Migration Plan

## Goal
Migrate the `CustomerFavorites` plugin from legacy Inertia.js tight coupling to a decoupled REST API + Headless TanStack React Query + Theme Override architecture registered into storefront slots (`'favorites.page'`, `'product.card.favorite'`).

## Tasks
- [x] Task 1: Run migration scaffolder script `python .agent/skills/plugin-inertia-to-api/scripts/migrate_plugin.py CustomerFavorite` → Verify: directory structure created under `resources/js/plugins/customer-favorites/` and `resources/js/themes/default/plugins/customer-favorites/`
- [x] Task 2: Implement dedicated `CustomerFavoritesApiController.php` under `app/Plugins/CustomerFavorites/src/Http/Controllers/Api/` and map routes in `app/Plugins/CustomerFavorites/routes/tenant.php` for `/api/customer-favorites` (index, sync, store) while maintaining backward-compatible `/favorites` web route → Verify: REST endpoints return pure `JsonResponse`
- [x] Task 3: Implement TypeScript types and TanStack Query hooks in `resources/js/plugins/customer-favorites/` (`useCustomerFavorites.ts`, `useFavoritesStorage.ts`, `useToggleFavoriteMutation.ts`) → Verify: cross-tab synchronized local storage + React Query v5 caching
- [x] Task 4: Implement neutral fallback components `DefaultCustomerFavorites.tsx` and `DefaultFavoriteButton.tsx` and export registration function `registerCustomerFavoritesPlugin()` → Verify: exports registered via `registerPluginDefault()`
- [x] Task 5: Implement themed presentation components in `resources/js/themes/default/plugins/customer-favorites/` (`ThemedCustomerFavorites.tsx`, `ThemedFavoriteButton.tsx`) and register theme overrides via `registerCustomerFavoritesThemeOverride()` → Verify: consumes headless hooks and design system
- [x] Task 6: Register customer-favorites in `resources/js/plugins/registry.ts` and mount dynamic `<PluginSlot name="favorites.page" pluginId="customer-favorites" />` in `AppRouter.tsx` and `resources/js/themes/default/pages/Favorites.tsx` → Verify: SPA shell and Inertia both leverage the decoupled slot bus
- [x] Task 7: Update `resources/js/hooks/useFavorites.ts` facade to delegate to the new headless plugin hook → Verify: existing components (`ProductCard.tsx`, `ProductShow.tsx`, etc.) work seamlessly without regression
- [x] Task 8: Update and execute full test suite and TypeScript verification → Verify: `npx tsc --noEmit`, `npm run build`, and `php artisan test tests/Feature/CustomerFavoritesTest.php` pass with 100% success

## Done When
- [x] `customer-favorites` data is served strictly via REST API (`/api/customer-favorites`)
- [x] Storefront renders favorites view dynamically via `<PluginSlot name="favorites.page" />`
- [x] Zero direct Inertia prop or hardcoded monolithic axios calls remain in `Favorites.tsx`
- [x] TypeScript verification (`npx tsc --noEmit`) and Vite build pass with 0 errors
- [x] Automated PHPUnit / Pest tests pass (all 6 tests, 32 assertions)
