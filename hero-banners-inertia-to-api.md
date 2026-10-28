# HeroBanners Inertia-to-API Migration Plan

## Goal
Migrate the `HeroBanners` plugin from legacy Inertia.js coupling to a decoupled REST API + Headless TanStack React Query + Theme Override architecture registered in the `'home.hero'` slot.

## Tasks
- [x] Task 1: Run migration scaffolder script `python .agent/skills/plugin-inertia-to-api/scripts/migrate_plugin.py HeroBanners` → Verify: directory structure created under `resources/js/plugins/hero-banners/` and `resources/js/themes/default/plugins/hero-banners/`
- [x] Task 2: Implement dedicated `HeroBannersApiController.php` under `app/Plugins/HeroBanners/src/Http/Controllers/Api/` and map routes in `app/Plugins/HeroBanners/routes/tenant.php` for `/api/hero-banners` (and keep backward-compatible `/api/hero-sliders`) → Verify: `curl` or PHP unit test returns 200 with JSON data
- [x] Task 3: Implement TypeScript types and TanStack Query hooks in `resources/js/plugins/hero-banners/` → Verify: types match backend model and query hook interacts with `@/core/api/client`
- [x] Task 4: Implement minimal neutral fallback component in `resources/js/plugins/hero-banners/components/DefaultHeroBanners.tsx` and export registration function `registerHeroBannersPlugin()` for slot `'home.hero'` → Verify: exported cleanly
- [x] Task 5: Move/adapt the full Embla Carousel into `resources/js/themes/default/plugins/hero-banners/ThemedHeroBanners.tsx` and register theme override in `resources/js/themes/default/plugins/hero-banners/index.ts` → Verify: theme override component consumes headless hook `useHeroBanners`
- [x] Task 6: Register plugins in app startup / theme bootstrap and replace direct `<HeroSlider slides={heroSlides} />` in `resources/js/themes/default/pages/HomePage.tsx` with `<PluginSlot name="home.hero" pluginId="hero-banners" />` → Verify: no direct imports of `HeroSlider` remain in `HomePage.tsx`
- [x] Task 7: Decouple backend `HomeController.php` by removing `HeroSlider::query()` and `heroSlides` prop → Verify: `HomeController.php` has no dependency on `HeroSlider`
- [x] Task 8: Run TypeScript check and test suite → Verify: `npx tsc --noEmit` and `php artisan test --filter=HeroBanners` pass

## Done When
- [x] `hero-banners` data is served strictly via REST API (`/api/hero-banners`)
- [x] Storefront renders hero banners dynamically via `<PluginSlot name="home.hero" />`
- [x] Zero Inertia prop coupling remains in `HomePage.tsx` and `HomeController.php`
- [x] TypeScript check and backend tests pass
