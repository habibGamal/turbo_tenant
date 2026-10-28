# Implementation Plan: Menu Localization, Top Search Sync, Mobile Cart Hide, and E2E Tests

## Overview
Optimize `/menu` for the default theme:
1. Ensure full i18n localization coverage in Arabic (`ar`) and English (`en`).
2. Remove duplicate in-page search bar on `/menu`, keep top navigation search active and pre-populated with URL search terms.
3. Hide the top navigation cart icon on mobile viewports (< 768px / `md:hidden`).
4. Extend and verify live E2E tests for category filters, price filters, sort, and infinite scroll.

---

## Task Breakdown

### Task 1: Complete i18n Localization Keys
- **Files**: `resources/js/locales/ar/translation.json`, `resources/js/locales/en/translation.json`
- **Action**: Add all missing keys discovered in `ThemedMenuPage.tsx` and navigation components (`searchProductsPlaceholder`, `activeFilters`, `clearAll`, `clearAllFilters`, `errorLoadingProducts`, `loadMore`, `loadingCategories`, `loadingMore`, `noProductsDescription`, `selected`, `storeMenu`, `nameAsc`, `nameDesc`, `priceAsc`, `priceDesc`).
- **Verification**: Run key verification script to ensure 0 missing keys.

### Task 2: Hide Cart Icon on Mobile in Top Navigation
- **Files**: `resources/js/themes/default/components/Navigation.tsx`
- **Action**: Wrap the `header.actions.cart` plugin slot in `<div className="hidden md:flex items-center">` so the cart icon with badge is hidden on mobile screens (< 768px) and visible on desktop/tablet.
- **Verification**: Inspect CSS classes and test across viewport sizes.

### Task 3: Sync Top SearchBar with URL & Menu Page
- **Files**: `resources/js/themes/default/components/navigation/SearchBar.tsx`, `resources/js/themes/default/components/Navigation.tsx`
- **Action**:
  - In `SearchBar.tsx`:
    - Read `searchParams.get("search")` when on `/menu` and initialize `searchQuery`.
    - Sync `searchQuery` when `searchParams` changes.
    - On form submit / Enter, if on `/menu`, preserve existing filter params while updating or removing `search`.
    - Provide a clear button (`X`) when `searchQuery` is present to easily clear the search term.
    - Set `data-testid="search-input"` (and alias `menu-search-input`).
  - In `Navigation.tsx`:
    - When `location.pathname === "/menu"`, auto-expand the mobile search input below the header so search is immediately visible and active.
- **Verification**: Verify search input shows existing query on `/menu?search=test`, form submit updates URL, and mobile dropdown is open on `/menu`.

### Task 4: Remove Duplicate In-Page Search from Menu Page
- **Files**: `resources/js/themes/default/plugins/catalog-products/ThemedMenuPage.tsx`
- **Action**: Remove the redundant in-page search bar and suggestions block (lines 429-465) from `ThemedMenuPage.tsx`. Keep the active filter chips section and category/price filters intact.
- **Verification**: Check DOM structure of `ThemedMenuPage.tsx` to confirm only one search bar exists (in the top navigation).

### Task 5: Extend and Verify E2E Tests
- **Files**: `tests/e2e/plugins/catalog-products/11-menu-page-browsing.live.spec.ts`
- **Action**:
  - Update `11-menu-page-browsing.live.spec.ts` to interact with the top navigation search input.
  - Verify category filtering, price range filtering, sort order, and infinite scrolling.
  - Add test asserting top cart button is hidden on mobile viewport (375x667) and visible on desktop (1280x720).
- **Verification**: Run `npx playwright test tests/e2e/plugins/catalog-products/11-menu-page-browsing.live.spec.ts` with 100% pass rate.
