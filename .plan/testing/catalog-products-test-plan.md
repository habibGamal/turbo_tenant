# Detailed Implementation & Test Plan: CatalogProducts Live E2E Test Suite

## Goal Description
Establish an exhaustive, live-only, theme-decoupled End-to-End (E2E) test suite for the `catalog-products` plugin in Turbo Tenant. The test suite thoroughly verifies product browsing, homepage section rendering, category navigation, real-time search autocompletion, product detail pages, variants selection, weighted items, extra addons, quantity steppers, quick-add modal flows, and 404 boundary conditions against the live multi-tenant environment (`http://kofe.localhost:8001`) with real database persistence.

All tests strictly adhere to `@[skills/e2e-test]`:
- **Strict Live-Mode Execution**: Live HTTP/API interactions with real database persistence on tenant `kofe`.
- **Decoupled Theme Architecture**: Theme Adapter Contract (`tests/e2e/themes/theme.contract.ts`) and standardized `data-testid` attributes; zero raw CSS selector leaks.
- **Single-Threaded Server Concurrency Rule**: `workers: 1`, `waitUntil: 'domcontentloaded'`, abort third-party tracking scripts.
- **Deterministic Response Synchronization**: Direct `page.waitForResponse(...)` on backend APIs (`/api/catalog-products/*`).

```
+──────────────────────────────────────────────────────────────────────────────────────────────────────────+
|                               CATALOG-PRODUCTS COMPREHENSIVE TEST MATRIX                                 |
+──────────────────────────────────────────────────────────────────────────────────────────────────────────+
   │
   ├── 01. STOREFRONT SECTIONS & PRODUCTS DISPLAY
   │   ├── 1.1 Live API bootstrap (/api/catalog-products/sections) renders configured section blocks
   │   ├── 1.2 Product cards render within sections with title, price, currency (EGP), and image
   │   ├── 1.3 Discount sale badge (Flame icon + "Sale" badge) renders for items with price_after_discount
   │   └── 1.4 Section header links ("View All") route correctly to section details
   │
   ├── 02. CATEGORY LISTINGS & NAVIGATION
   │   ├── 2.1 Fetch active categories (/api/catalog-products/categories)
   │   ├── 2.2 Category cards/tabs render with category title, image, and products count badge
   │   └── 2.3 Category selection filters catalog or navigates with category parameter
   │
   ├── 03. LIVE AUTOCOMPLETION & SEARCH SUGGESTIONS
   │   ├── 3.1 Typing >= 2 characters in header search triggers GET /api/catalog-products/suggestions
   │   ├── 3.2 Suggestions dropdown card displays matching products (name, Arabic name, price, thumbnail)
   │   ├── 3.3 Keyboard/click selection from suggestions routes directly to /products/:id
   │   └── 3.4 Typing < 2 characters dismisses suggestions dropdown
   │
   ├── 04. PRODUCT DETAIL PAGE & META INFORMATION
   │   ├── 4.1 Card click navigates to /products/:id and renders product-details-container
   │   ├── 4.2 Breadcrumb navigation renders "Home / Category / Product Name" with working links
   │   ├── 4.3 Product gallery/image display and ProductInfo (title, category badge, description)
   │   └── 4.4 Tabbed content: Reviews and Details tabs toggle smoothly
   │
   ├── 05. VARIANT SELECTION & DYNAMIC PRICING
   │   ├── 5.1 Product with variants (e.g. Simple Coffee [id: 5]: "سادة" vs "Mid") renders radio group
   │   ├── 5.2 Switching between variants updates active visual state and radio checked indicator
   │   └── 5.3 Variant price difference (+price) is reflected in effective product total
   │
   ├── 06. WEIGHTED ITEMS & DISCRETE WEIGHT SELECTION
   │   ├── 6.1 Product with sell_by_weight=true renders discrete weight options (e.g. 125g, 250g, 500g, 1kg)
   │   ├── 6.2 Selecting weight option updates unit calculation and displayed total price
   │   └── 6.3 Product card indicates "per Kg" label for weighted products
   │
   ├── 07. EXTRAS & ADDONS CUSTOMIZATION
   │   ├── 7.1 Products linked to ExtraOption group (e.g. "Gensing 1 g" +10 EGP) render extra checkboxes
   │   ├── 7.2 Checkbox toggle activates extra option and increments total price
   │   ├── 7.3 When allow_quantity=true, extra quantity steppers (-) and (+) allow multi-unit addons
   │   └── 7.4 Extra stepper lower boundary: (-) button disabled when extra quantity is 1
   │
   ├── 08. QUANTITY STEPPERS & PRICE CALCULATION
   │   ├── 8.1 Regular quantity selector increments (+) and decrements (-) line quantity
   │   ├── 8.2 Decrement button disabled when quantity is 1 (boundary protection)
   │   └── 8.3 Total price dynamically computes: (EffectivePrice + ExtrasTotal) * Quantity
   │
   ├── 09. QUICK-ADD & OPTIONS MODAL FLOW
   │   ├── 9.1 Clicking Add to Cart from product card for complex product opens ProductOptionsModal
   │   ├── 9.2 Customizing variant/weight/extras within modal and clicking Add to Cart dispatches to cart API
   │   └── 9.3 Modal closes upon successful addition and header cart badge increments
   │
   └── 10. ERROR STATES & 404 BOUNDARIES
       ├── 10.1 Navigating to non-existent product ID (/products/999999) returns 404 from API
       ├── 10.2 UI renders graceful "Product Not Found" fallback without crashing SPA shell
       ├── 10.3 "Browse Menu" fallback button successfully redirects user back to catalog/menu
       └── 10.4 Empty suggestions query gracefully closes dropdown without error
```

---

## User Review Required

> [!IMPORTANT]
> **Key Architectural Decisions for CatalogProducts Test Suite:**
> 1. **Modular 10-Suite Structure**:
>    Following the architecture established for `cart-engine` and `order-management`, `catalog-products` will be structured into a dedicated folder `tests/e2e/plugins/catalog-products/` containing 10 isolated, fast, single-concern specs (`01-*.live.spec.ts` through `10-*.live.spec.ts`). The legacy minimal file `tests/e2e/plugins/catalog-products.live.spec.ts` will be replaced.
> 2. **Theme Adapter Contract Enrichment**:
>    `ICatalogTheme` and `BaseCatalogTheme` will be enriched with standard methods:
>    - `getQuantityIncrementButton()`, `getQuantityDecrementButton()`, `getQuantityValue()`
>    - `getSearchInput()`, `getSearchSuggestions()`, `getSearchSuggestionItem(index)`
>    - `getNotFoundMessage()`, `getBrowseMenuButton()`
>    - `getOptionsModal()`, `getModalAddToCartButton()`
> 3. **Component Test ID Instrumentation**:
>    - Add `data-testid="product-details-container"` to `ThemedProductShow.tsx`
>    - Add `data-testid="product-quantity-decrement"`, `data-testid="product-quantity-increment"`, and `data-testid="product-quantity-value"` to `ProductOptions.tsx`
>    - Add `data-testid="search-input"` and `data-testid="search-suggestion-item"` to `SearchBar.tsx`
>    - Add `data-testid="category-tab"` / `data-testid="category-card"` to `CategoriesSection.tsx`

---

## Open Questions

> [!NOTE]
> Please review the interactive questions below regarding test scope, modal vs full-page preferences, and test suite structure.

---

## Proposed Changes

### Component Instrumentation & Test Identifiers

#### [MODIFY] [ThemedProductShow.tsx](file:///e:/turbo_tenant/resources/js/themes/default/plugins/catalog-products/ThemedProductShow.tsx)
- Add `data-testid="product-details-container"` to main product container.
- Add `data-testid="product-not-found"` to the 404 fallback container.
- Add `data-testid="browse-menu-button"` to the 404 redirect button.

#### [MODIFY] [ProductOptions.tsx](file:///e:/turbo_tenant/resources/js/themes/default/plugins/catalog-products/components/ProductOptions.tsx)
- Add `data-testid="product-quantity-decrement"` to the `-` stepper button.
- Add `data-testid="product-quantity-increment"` to the `+` stepper button.
- Add `data-testid="product-quantity-value"` to the quantity display.

#### [MODIFY] [SearchBar.tsx](file:///e:/turbo_tenant/resources/js/themes/default/components/navigation/SearchBar.tsx)
- Add `data-testid="search-input"` to the search text input.
- Add `data-testid="search-suggestions-container"` to the suggestions card container.
- Add `data-testid="search-suggestion-item"` to individual product suggestion rows.

#### [MODIFY] [CategoriesSection.tsx](file:///e:/turbo_tenant/resources/js/themes/default/plugins/catalog-products/components/CategoriesSection.tsx)
- Add `data-testid="category-card"` / `data-testid="category-tab"` to each category item.

---

### Theme Contract & Adapter Layer

#### [MODIFY] [theme.contract.ts](file:///e:/turbo_tenant/tests/e2e/themes/theme.contract.ts)
- Extend `ICatalogTheme` interface:
  ```typescript
  export interface ICatalogTheme {
    getCategoryTabs(): Locator;
    getProductCards(): Locator;
    getProductCard(index: number): Locator;
    getProductTitle(card: Locator): Locator;
    getProductPrice(card: Locator): Locator;
    getAddToCartButton(card: Locator): Locator;
    getFavoriteToggleButton(card: Locator): Locator;
    getProductDetailsContainer(): Locator;
    getProductOptionSelectors(): Locator;
    getVariantOptions(): Locator;
    getWeightOptions(): Locator;
    getExtraCheckboxes(): Locator;
    getExtraIncrementButtons(): Locator;
    getExtraDecrementButtons(): Locator;
    getDetailAddToCartButton(): Locator;
    // New additions:
    getQuantityIncrementButton(): Locator;
    getQuantityDecrementButton(): Locator;
    getQuantityValue(): Locator;
    getSearchInput(): Locator;
    getSearchSuggestions(): Locator;
    getSearchSuggestionItem(index: number): Locator;
    getNotFoundMessage(): Locator;
    getBrowseMenuButton(): Locator;
    getOptionsModal(): Locator;
  }
  ```

#### [MODIFY] [BaseThemeAdapter.ts](file:///e:/turbo_tenant/tests/e2e/themes/base/BaseThemeAdapter.ts)
- Implement all new getters in `BaseCatalogTheme` using standardized `data-testid` selectors.

#### [MODIFY] [THEME_TEST_IDS_SPEC.md](file:///e:/turbo_tenant/docs/THEME_TEST_IDS_SPEC.md)
- Document new attributes in Section 3 (Catalog Products Plugin) and Section 1 (Header/Search).

---

### E2E Test Suite Creation

#### [NEW] `tests/e2e/plugins/catalog-products/01-sections-display.live.spec.ts`
- Tests homepage sections rendering, product cards, titles, prices, currency formatting, and sale badges.

#### [NEW] `tests/e2e/plugins/catalog-products/02-categories-navigation.live.spec.ts`
- Tests category cards, image fallbacks, count badges, and category routing.

#### [NEW] `tests/e2e/plugins/catalog-products/03-search-suggestions.live.spec.ts`
- Tests search input, autocompletion API triggers, suggestions list, and suggestion navigation.

#### [NEW] `tests/e2e/plugins/catalog-products/04-product-details.live.spec.ts`
- Tests navigation to `/products/:id`, breadcrumb rendering, product images, rating, and description.

#### [NEW] `tests/e2e/plugins/catalog-products/05-variants-selection.live.spec.ts`
- Tests variant radio choices on `Simple Coffee`, active styling, radio checking, and variant pricing update.

#### [NEW] `tests/e2e/plugins/catalog-products/06-weight-options.live.spec.ts`
- Tests discrete weight values (125g, 250g, 500g, 1kg), radio selection, and dynamic weight price calculation.

#### [NEW] `tests/e2e/plugins/catalog-products/07-addons-and-extras.live.spec.ts`
- Tests extra checkbox toggling ("Gensing 1 g" +10 EGP), extra quantity steppers, and price additions.

#### [NEW] `tests/e2e/plugins/catalog-products/08-quantity-and-pricing.live.spec.ts`
- Tests quantity steppers (`+` / `-`), lower boundary enforcement (disabled at 1), and composite price calculation.

#### [NEW] `tests/e2e/plugins/catalog-products/09-quick-add-and-modal.live.spec.ts`
- Tests product card add-to-cart button, `ProductOptionsModal` opening, customization, and submission to cart.

#### [NEW] `tests/e2e/plugins/catalog-products/10-error-states-and-boundaries.live.spec.ts`
- Tests `/products/999999` 404 response, graceful "Product Not Found" fallback, and "Browse Menu" navigation.

#### [DELETE] `tests/e2e/plugins/catalog-products.live.spec.ts`
- Replace legacy 2-test single spec with the modular 10-suite directory.

---

## Verification Plan

### Automated Tests
```powershell
# 1. Compile frontend assets with new data-testids
npm run build

# 2. Run the complete CatalogProducts E2E Suite
npx playwright test tests/e2e/plugins/catalog-products

# 3. Run individual spec for debugging/headed verification if needed
npx playwright test tests/e2e/plugins/catalog-products/01-sections-display.live.spec.ts --headed

# 4. Regression check on dependent suites
npx playwright test tests/e2e/plugins/cart-engine/01-add-to-cart.live.spec.ts
```

### Manual Verification
1. Open `http://kofe.localhost:8001` in browser.
2. Verify homepage sections display products properly with image, title, price, and badges.
3. Type in header search bar, verify live suggestions card appears with image and price, click a suggestion.
4. On `/products/5`, test clicking variants, weight options, extras, and quantity steppers; observe total price recalculation.
5. Visit `/products/999999` and verify the graceful "Product Not Found" alert and working "Browse Menu" button.
