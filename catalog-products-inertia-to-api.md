# CatalogProducts: Inertia to API & Headless React Migration

## Goal
Migrate the `CatalogProducts` plugin from legacy Inertia.js tight-coupling to decoupled REST API (`/api/catalog-products/*`), Headless React TanStack Query hooks, Default Fallback UI, and Theme Slot Overrides.

## Tasks
- [ ] Task 1: Run automated scaffolding script `python .agent/skills/plugin-inertia-to-api/scripts/migrate_plugin.py CatalogProducts` → Verify: files generated in `app/Plugins/CatalogProducts/src/Http/Controllers/Api/` and `resources/js/plugins/catalog-products/`
- [ ] Task 2: Implement REST API endpoints in `CatalogProductsApiController.php` (index, show, sections, categories, search) → Verify: PHP syntax valid and returns `JsonResponse`
- [ ] Task 3: Configure routes in `app/Plugins/CatalogProducts/routes/tenant.php` under `/api/catalog-products/*` and remove Inertia renders → Verify: `php artisan route:list` shows clean API routes
- [ ] Task 4: Define TypeScript interfaces in `resources/js/plugins/catalog-products/types/index.ts` → Verify: types cover Product, Category, Variant, ExtraOption, Section, and API Responses
- [ ] Task 5: Implement TanStack Query v5 headless hooks in `resources/js/plugins/catalog-products/api/` (`useCatalogProducts`, `useCatalogProduct`, `useCatalogSections`, `useCatalogCategories`, `useCatalogSearch`) → Verify: hooks use `@/core/api/client` and TypeScript compiles
- [ ] Task 6: Implement fallback components & slot registration in `resources/js/plugins/catalog-products/` (`DefaultProductsSection`, `DefaultProductDetails`, `index.ts`) → Verify: `registerCatalogProductsPlugin` registers defaults to slot registry
- [ ] Task 7: Implement Theme Overrides in `resources/js/themes/default/plugins/catalog-products/` (`ThemedProductsSection`, `ThemedProductDetails`, `index.ts`) → Verify: registers overrides to 'default' theme
- [ ] Task 8: Wire up plugin bootstrap and update `HomePage.tsx` to use `<PluginSlot>` → Verify: HomePage renders slot without direct Inertia dependency
- [ ] Task 9: Verify TypeScript build and validate runtime integrity → Verify: `npx tsc --noEmit` passes with 0 errors

## Done When
- [ ] Backend controllers return pure `JsonResponse` under `/api/catalog-products/*`.
- [ ] No `Inertia::render` remains in `CatalogProducts` controllers.
- [ ] Headless hooks cleanly fetch data using TanStack Query v5.
- [ ] Slot registry enables both default fallbacks and default theme overrides.
- [ ] TypeScript check (`npx tsc --noEmit`) passes cleanly.
