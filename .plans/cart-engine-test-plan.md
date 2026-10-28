# Detailed Test Plan: CartEngine Plugin Functional Scenarios (Expanded)

## Goal Description
Conduct an exhaustive audit and establish a 10-suite modular live E2E test plan for the `cart-engine` plugin in Turbo Tenant. The test plan thoroughly covers standard product flows, **product variants**, **weighted items** (discrete weight values and multipliers), and **addons/extras** (item customizations and extra quantities) across the backend services, pricing calculators, and React storefront UI.

```
+──────────────────────────────────────────────────────────────────────────────────────────────────────────+
|                                  CART-ENGINE COMPREHENSIVE TEST MATRIX                                   |
+──────────────────────────────────────────────────────────────────────────────────────────────────────────+
   │
   ├── 01. ADD TO CART & BADGE SYNC
   │   ├── 1.1 Add standard product from product card on catalog
   │   ├── 1.2 Add multiple distinct products -> badge counter increments accurately
   │   └── 1.3 Navbar Cart Button bump animation and state synchronization
   │
   ├── 02. PRODUCT VARIANTS (e.g., Simple Coffee: "Mid" vs "سادة")
   │   ├── 2.1 Select variant on product details modal or /products/:id
   │   ├── 2.2 Adding two different variants of same product creates distinct line items
   │   ├── 2.3 Adding same variant twice increments quantity of that specific line item
   │   └── 2.4 Cart item display verifies variant badge/label and effective variant price
   │
   ├── 03. WEIGHTED ITEMS (sell_by_weight: 125g, 250g, 500g, 1kg)
   │   ├── 3.1 Select weight option value on product details modal
   │   ├── 3.2 Weight-based subtotal calculation: (base_price * weight_kg * multiplier)
   │   ├── 3.3 Dynamic weight dropdown change inside CartItem (<Select>) triggers live API update
   │   ├── 3.4 Multiplier stepper: Increment/decrement weight_multiplier (1x 250g -> 2x 250g)
   │   └── 3.5 Boundary enforcement: Decrement disabled when weight_multiplier <= 1
   │
   ├── 04. ADDONS & EXTRAS (e.g., "Gensing 1 g" +10 EGP, allow_quantity: true)
   │   ├── 4.1 Toggle extra checkbox on product options modal
   │   ├── 4.2 Extra quantity stepper: Increment extra quantity (1x, 2x, 3x) before adding
   │   ├── 4.3 Addon pricing math: (base_price + (extra_price * extra_qty)) * quantity
   │   ├── 4.4 Cart display verification: Line item shows "Extras: 2x Gensing 1 g"
   │   └── 4.5 Ultra Combo: Product with Variant + Weight Option + Addon (Full matrix verification)
   │
   ├── 05. QUANTITY MANAGEMENT & BOUNDARY RULES (Standard Items)
   │   ├── 5.1 Increment line item quantity via (+) button -> recalculate item total & subtotal
   │   ├── 5.2 Decrement line item quantity via (-) button -> recalculate item total & subtotal
   │   └── 5.3 Lower boundary enforcement: (-) button disabled when quantity is 1
   │
   ├── 06. LINE ITEM REMOVAL
   │   ├── 6.1 Remove specific variant/item from multi-item cart -> verify remaining items & total
   │   └── 6.2 Remove last remaining item -> verify automatic transition to empty cart view
   │
   ├── 07. CLEAR CART
   │   ├── 7.1 Purge entire cart via "Clear Cart" button (data-testid="clear-cart-button")
   │   └── 7.2 Verify backend deletes cart session and clears header badge count
   │
   ├── 08. EMPTY CART EXPERIENCE
   │   ├── 8.1 Navigate to /cart when empty -> verify illustration, copy, and layout
   │   └── 8.2 Click "Start Shopping" button -> verify redirect to catalog or home
   │
   ├── 09. PERSISTENCE & SESSION CONTINUITY
   │   ├── 9.1 Browser page reload (F5 / page.reload()) preserves variants, weights, and addons
   │   └── 9.2 Client-side router navigation (Home -> Product -> Cart -> Home) preserves state
   │
   └── 10. CHECKOUT INTEGRATION & GUARDS
       ├── 10.1 "Proceed to Checkout" button routes to /checkout with variants, weights, and extras intact
       └── 10.2 Visiting /checkout with 0 items guards against checkout and redirects back
```

---

## Codebase Audit & Technical Discoveries

### 1. Database & Live Catalog Inspection (Tenant: `kofe`)
- **Total Products in Store**: 38 products.
- **Products with Variants (10)**: Product `[5]` (`Simple Coffee`), `[6]` (`Ground Coffee`), `[7]`, etc. have variant choices like `"Mid"` and `"سادة"`.
- **Products Sold by Weight (31)**: Products configured with `sell_by_weight = true` and `WeightOption` unit `kg` (`125 جرام` = 0.125 kg, `250 جرام` = 0.250 kg, `500 جرام` = 0.500 kg, `كيلو` = 1.000 kg).
- **Products with Addons/Extras (1)**: Product `[5]` (`Simple Coffee`) links to `ExtraOption` group `"addons"` with item `"Gensing 1 g"` (+10 EGP, `allow_quantity = true`).
- **All-in-One Gold Test Target**: **Product `[5]` (`Simple Coffee`)** is configured with **Variants**, **Weight Options**, and **Addons** simultaneously, providing an end-to-end integration target!

### 2. Pricing Calculations (`CartPriceCalculator.php`)
The backend calculates subtotal using strict mathematical rules:
1. **Effective Price**:
   $$\text{Price} = \text{variant->price} \;\text{if present, else}\; (\text{price\_after\_discount} \;\text{or}\; \text{base\_price})$$
2. **Extras Total**:
   $$\text{ExtrasTotal} = \sum (\text{extraOptionItem->price} \times \text{extra->quantity})$$
3. **Subtotal for Standard Items**:
   $$\text{Subtotal} = (\text{Price} + \text{ExtrasTotal}) \times \text{Quantity}$$
4. **Subtotal for Weighted Items (`sell_by_weight = true`)**:
   $$\text{FinalQuantity} = \text{weight\_multiplier} \times \text{weightOptionValue->value}$$
   $$\text{Subtotal} = (\text{Price} \times \text{FinalQuantity}) + (\text{ExtrasTotal} \times \text{weight\_multiplier})$$

### 3. Storefront UI Controls & Selectors
- **Variant Selector**: Radio group rendered inside `ProductOptions.tsx`.
- **Weight Selector (Product Page/Modal)**: Radio group rendered inside `ProductOptions.tsx` displaying weight labels and units.
- **Weight Selector (Cart Line Item)**: Dropdown `<Select>` rendered inside `CartItem.tsx` (`id="weight-${item.id}"`), firing `onWeightChange` to update `weight_option_value_id` live.
- **Addon Checkboxes & Quantities**: Checkbox with optional `(-)` / `(+)` steppers rendered inside `ProductOptions.tsx`.
- **Cart Line Item Details**: `CartItem.tsx` renders variant label under product title, weight select trigger, and extras list `"Extras: 2x Gensing 1 g"`.

---

## User Review Required

> [!IMPORTANT]
> **Key Architectural Decisions for the Expanded Suite:**
> 1. **Modular 10-Suite Structure**:
>    To keep each scenario isolated and blazing fast (~1-3 seconds per file), tests are structured into 10 separate spec files in `tests/e2e/plugins/cart-engine/`.
> 2. **Standard Theme Test IDs for Variants, Weights & Addons**:
>    To guarantee that future themes pass without modifying tests, we will document and standardize the following attributes in `docs/THEME_TEST_IDS_SPEC.md`:
>    - `data-testid="product-variant-option"` (Variant radio option)
>    - `data-testid="product-weight-option"` (Weight radio option on product page)
>    - `data-testid="cart-item-weight-select"` (Weight select dropdown in cart item)
>    - `data-testid="extra-option-checkbox"` (Addon checkbox)
>    - `data-testid="extra-qty-increment"` / `data-testid="extra-qty-decrement"` (Addon quantity steppers)
>    - `data-testid="cart-item-variant"` (Variant text on cart item)
>    - `data-testid="cart-item-extras"` (Addon text on cart item)
>    - `data-testid="clear-cart-button"` (Header clear cart CTA)
>    - `data-testid="start-shopping-button"` (Empty cart CTA)

---

## Detailed Scenario Breakdown (10 Suites)

### Suite 1: Add to Cart & Badge Sync (`01-add-to-cart.live.spec.ts`)
- **1.1 Standard Product Add**: Click "Add to Cart" on catalog card -> Cart badge increments to `1`.
- **1.2 Multiple Products Add**: Add two distinct products -> Badge increments to `2`.
- **1.3 Button State**: Add to cart shows loading feedback and cart icon button bump animation.

### Suite 2: Product Variants (`02-variants.live.spec.ts`)
- **2.1 Variant Selection**: Open product modal for Product `[5]` (`Simple Coffee`) -> Select variant `"سادة"` -> Add to cart.
- **2.2 Distinct Line Items**: Add Product `[5]` with variant `"سادة"`, then add Product `[5]` with variant `"Mid"` -> Verify cart renders **two distinct line items** in `/cart`.
- **2.3 Same Variant Aggregation**: Add Product `[5]` with variant `"سادة"` again -> Verify the `"سادة"` line item quantity increments while `"Mid"` remains unchanged.
- **2.4 Variant Cart Display**: Verify each item renders `data-testid="cart-item-variant"` with correct variant text and price.

### Suite 3: Weighted Items (`03-weighted-items.live.spec.ts`)
- **3.1 Weight Option Selection**: Open Product `[5]` (`sell_by_weight`) -> Select `250 جرام` (0.250 kg) -> Add to cart.
- **3.2 Subtotal Math**: Line subtotal matches $\text{base\_price} \times 0.250$.
- **3.3 Dynamic In-Cart Weight Change**: Open `/cart` -> Click weight select trigger `data-testid="cart-item-weight-select"` -> Select `500 جرام` -> Verify API request updates `weight_option_value_id` and subtotal doubles live.
- **3.4 Weight Multiplier Stepper**: Click `(+)` on cart item -> Multiplier increases to `2` (representing $2 \times 500\text{g} = 1.0\text{kg}$) -> Subtotal doubles.
- **3.5 Multiplier Boundary**: Click `(-)` until multiplier reaches `1` -> `(-)` button is disabled.

### Suite 4: Addons & Extras (`04-addons-and-extras.live.spec.ts`)
- **4.1 Addon Toggle**: Open Product `[5]` -> Check addon `"Gensing 1 g"` (+10 EGP).
- **4.2 Addon Quantity Stepper**: Click extra increment button `data-testid="extra-qty-increment"` to set extra quantity to `2`.
- **4.3 Add to Cart & Pricing Math**: Add to cart -> Verify item in `/cart` displays `data-testid="cart-item-extras"` with text `"2x Gensing 1 g"`.
- **4.4 Addon Subtotal**: Verify line total includes $(10 \times 2) = 20\text{ EGP}$ extra cost.
- **4.5 Ultra Combo Verification**: Add Product `[5]` with Variant `"سادة"` + Weight `250 جرام` + Addon `2x Gensing 1 g` -> Verify full end-to-end mathematical fidelity.

### Suite 5: Quantity Management & Boundaries (`05-quantity-management.live.spec.ts`)
- **5.1 Standard Quantity Increment**: Click `(+)` -> Quantity changes $1 \to 2$ -> Subtotal and total double.
- **5.2 Standard Quantity Decrement**: Click `(-)` on item with quantity `2` -> Quantity changes $2 \to 1$.
- **5.3 Boundary Rule**: At quantity `1`, `(-)` button is disabled. Clicking does not decrement to 0 or negative.

### Suite 6: Line Item Removal (`06-line-item-removal.live.spec.ts`)
- **6.1 Multi-Item Removal**: Multi-item cart with Item A & Item B -> Remove Item A -> Item A is removed; Item B remains; subtotal updates.
- **6.2 Last Item Removal**: Remove last item -> Immediate transition to Empty Cart UI; badge disappears.

### Suite 7: Clear Cart (`07-clear-cart.live.spec.ts`)
- **7.1 Purge Action**: Click `data-testid="clear-cart-button"` on `/cart` -> Cart purged in single operation.
- **7.2 Backend & Badge Sync**: Backend deletes cart session; header cart badge updates to 0.

### Suite 8: Empty Cart Experience (`08-empty-cart.live.spec.ts`)
- **8.1 Direct Navigation**: Navigate directly to `/cart` with no items -> Verify empty state illustration and message.
- **8.2 Start Shopping CTA**: Click `data-testid="start-shopping-button"` -> Redirects to catalog.

### Suite 9: Persistence & Session Continuity (`09-persistence-continuity.live.spec.ts`)
- **9.1 Page Reload Resilience**: Add items with variant, weight, and addon -> Run `page.reload()` -> All items, options, and subtotals remain identical.
- **9.2 Router Navigation Continuity**: Navigate `Home -> Product -> Cart -> Favorites -> Cart` -> Cart state persists seamlessly without data loss.

### Suite 10: Checkout Integration & Guards (`10-checkout-integration.live.spec.ts`)
- **10.1 Checkout Handoff**: Cart with configured items -> Click `Proceed to Checkout` -> Navigates to `/checkout` with items intact in order summary.
- **10.2 Empty Checkout Guard**: Visit `/checkout` with empty cart -> Guard redirects back to `/cart` or `/`.

---

## Proposed Implementation Steps

### 1. Documentation & Theme Contract Updates
- **Update** `docs/THEME_TEST_IDS_SPEC.md` with:
  - `product-variant-option`
  - `product-weight-option`
  - `cart-item-weight-select`
  - `extra-option-checkbox`
  - `extra-qty-increment` / `extra-qty-decrement`
  - `cart-item-variant`
  - `cart-item-extras`
  - `clear-cart-button`
  - `start-shopping-button`
- **Extend** `tests/e2e/themes/theme.contract.ts`:
  - Add variant, weight, addon, clear cart, and start shopping accessors to `ICatalogTheme` and `ICartTheme`.
- **Implement** methods in `tests/e2e/themes/base/BaseThemeAdapter.ts`.

### 2. Standardize Default Theme Component Selectors
- **Edit** `resources/js/themes/default/plugins/catalog-products/components/ProductOptions.tsx`:
  - Add `data-testid="product-variant-option"` on variant labels.
  - Add `data-testid="product-weight-option"` on weight labels.
  - Add `data-testid="extra-option-checkbox"` on extra checkboxes.
  - Add `data-testid="extra-qty-increment"` and `data-testid="extra-qty-decrement"` on addon quantity buttons.
- **Edit** `resources/js/themes/default/plugins/cart-engine/components/CartItem.tsx`:
  - Add `data-testid="cart-item-variant"` on variant container.
  - Add `data-testid="cart-item-weight-select"` on weight SelectTrigger.
  - Add `data-testid="cart-item-extras"` on extras container.
- **Edit** `resources/js/themes/default/plugins/cart-engine/ThemedCart.tsx`:
  - Add `data-testid="clear-cart-button"`.
- **Edit** `resources/js/themes/default/plugins/cart-engine/components/EmptyCart.tsx`:
  - Add `data-testid="start-shopping-button"`.
- **Rebuild assets**: `npm run build`.

### 3. Implement Modular Test Specs (`tests/e2e/plugins/cart-engine/`)
Create the 10 spec files:
1. `tests/e2e/plugins/cart-engine/01-add-to-cart.live.spec.ts` - **COMPLETED & PASSED**
2. `tests/e2e/plugins/cart-engine/02-variants.live.spec.ts` - **COMPLETED & PASSED**
3. `tests/e2e/plugins/cart-engine/03-weighted-items.live.spec.ts` - **COMPLETED & PASSED**
4. `tests/e2e/plugins/cart-engine/04-addons-and-extras.live.spec.ts` - **COMPLETED & PASSED**
5. `tests/e2e/plugins/cart-engine/05-quantity-management.live.spec.ts` - **COMPLETED & PASSED**
6. `tests/e2e/plugins/cart-engine/06-line-item-removal.live.spec.ts` - **COMPLETED & PASSED**
7. `tests/e2e/plugins/cart-engine/07-clear-cart.live.spec.ts` - **COMPLETED & PASSED**
8. `tests/e2e/plugins/cart-engine/08-empty-cart.live.spec.ts` - **COMPLETED & PASSED**
9. `tests/e2e/plugins/cart-engine/09-persistence-continuity.live.spec.ts` - **COMPLETED & PASSED**
10. `tests/e2e/plugins/cart-engine/10-checkout-integration.live.spec.ts` - **COMPLETED & PASSED**

---

## Verification Results

All 17 tests passed across all 10 scenario suites:

```bash
> npx playwright test tests/e2e/plugins/cart-engine

Running 17 tests using 1 worker

  ok  1 [theme-default] › 01-add-to-cart.live.spec.ts › adds standard product to cart and increments header badge (7.1s)
  ok  2 [theme-default] › 01-add-to-cart.live.spec.ts › adds multiple distinct products and synchronizes badge count (11.5s)
  ok  3 [theme-default] › 02-variants.live.spec.ts › selects variant on product details page and renders variant badge in cart (7.5s)
  ok  4 [theme-default] › 02-variants.live.spec.ts › creates separate line items for distinct variants and aggregates same variant (15.5s)
  ok  5 [theme-default] › 03-weighted-items.live.spec.ts › calculates weighted price, handles dynamic weight changes, and enforces multiplier boundaries (10.3s)
  ok  6 [theme-default] › 04-addons-and-extras.live.spec.ts › toggles extra option, increments addon quantity, and verifies subtotal math in cart (6.8s)
  ok  7 [theme-default] › 04-addons-and-extras.live.spec.ts › verifies ultra combo (variant + weight + addon) price accuracy in cart (5.6s)
  ok  8 [theme-default] › 05-quantity-management.live.spec.ts › increments and decrements standard item quantity and enforces lower boundary (10.1s)
  ok  9 [theme-default] › 06-line-item-removal.live.spec.ts › removes item from multi-item cart and verifies subtotal and badge update (17.8s)
  ok 10 [theme-default] › 07-clear-cart.live.spec.ts › purges entire cart via Clear Cart button and syncs empty state (7.5s)
  ok 11 [theme-default] › 08-empty-cart.live.spec.ts › displays empty state illustration and navigates back to store on Start Shopping CTA (4.5s)
  ok 12 [theme-default] › 09-persistence-continuity.live.spec.ts › preserves configured item across hard page reloads (10.4s)
  ok 13 [theme-default] › 09-persistence-continuity.live.spec.ts › maintains cart state across multi-page client router transitions (11.9s)
  ok 14 [theme-default] › 10-checkout-integration.live.spec.ts › hands off configured cart items to checkout page with matching summary (7.7s)
  ok 15 [theme-default] › 10-checkout-integration.live.spec.ts › guards checkout page when cart has 0 items and displays empty cart notice (2.3s)
  ok 16 [theme-default] › cart-engine.live.spec.ts › adds product to cart via UI and verifies cart drawer/page interaction (5.2s)
  ok 17 [theme-default] › cart-engine.live.spec.ts › displays empty cart state when no items in cart (2.6s)

  17 passed (2.5m)
```
