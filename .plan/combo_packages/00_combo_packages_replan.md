# Combo Meals & Packages — Current State and Replan

Date: 2026-11-05 · Plugin: `combo-packages` (`app/Plugins/ComboPackages`, `resources/js/plugins/combo-packages`)

---

## 1. Where it stands today

**Short version: you can define a combo in the admin and show it on the home page, but nobody can buy one.**
No cart, order, pricing, coupon, or POS code knows packages exist (a grep for `package|combo` across
CartEngine, OrderManagement, PosIntegration, CouponsPromotions and CatalogProducts finds nothing).

| Layer | Exists | State |
|---|---|---|
| Schema | `packages`, `package_groups`, `package_items` | Decent base: groups support `all` / `choose_one` / `choose_multiple`, min/max, per-item variant, qty, `price_adjustment`, `is_default`. |
| Admin (Filament) | Create/edit/list, nested repeaters | Works, but has no validation of the selection rules and no image (see bugs). |
| API | `GET /api/packages`, `GET /api/packages/{id}` | Read-only. Filters are wrong (see bugs). |
| Storefront | Home section + card | The "Select Package" button does nothing. No detail page, no configurator. |
| Cart | — | `cart_items.product_id` is NOT NULL and `CartService::addItem` only takes products. |
| Orders | — | `order_items.product_id` is NOT NULL. No component snapshot. |
| Pricing | — | `CartPriceCalculator` only handles products. |
| POS | — | `POSProductResolver` maps product/variant/extra only. But the POS API already supports the combo shape: one item with many `posRefObj` entries (`CREATE_ORDER_API_DOC.md` §items). |
| Coupons | — | `applicable_to` only supports product_ids / category_ids. |
| Tests | 6 feature tests (model + API), 1 UX test, 1 e2e render test | Nothing covers selection, pricing, or purchasing. |

### 1.1 Bugs and defects in what exists

| # | Severity | Where | Problem |
|---|---|---|---|
| B1 | **High** | `package_items.product_id` → `cascadeOnDelete` (Product has no SoftDeletes) | Deleting a product silently removes it from every combo. A fixed "burger + fries + drink" becomes "fries + drink" at the same price, and a `choose_one` group can end up with no options. |
| B2 | **High** | `StorefrontData::index()` | Filters only on `is_active`. It ignores `valid_from`/`valid_until`, so expired and scheduled combos show up. |
| B3 | **High** | `PackageApiController::show()` | No filtering at all. Inactive and expired packages are readable by id. |
| B4 | High | `ComboPackagesServiceProvider::registerRelations()` + `Package::products()` | Both point at `package_product`, which migration `2025_11_23` drops. Calling `$product->packages` throws. |
| B5 | High | `resources/js/plugins/combo-packages/types/index.ts` | Types don't match the API. TS has `min_quantity`/`max_quantity`, API sends `min_selections`/`max_selections`. TS is missing `selection_type`, item `quantity`, `price_adjustment`, `is_default`, `original_price`, `badge*`, `valid_*`, `description_ar`. TS has `image`, which the API never sends. |
| B6 | Medium | API resources | Components that are inactive (product) or unavailable (variant) are still sent to the storefront. |
| B7 | Medium | `PackageForm` | No rule validation. Nothing checks min ≤ max ≤ option count, `choose_one` ⇒ 1/1, at least one item per group, at least one group, number of defaults within [min, max], duplicate product+variant in a group, `valid_until > valid_from`, or price > 0. |
| B8 | Medium | `PackageForm` | `original_price` and `discount_percentage` are typed in by hand, so they can contradict the real à-la-carte value. |
| B9 | Medium | `packages` table | No `image` column, and both themes render `packageItem.image`. |
| B10 | Low | `PackageForm` | Product options are loaded eagerly (`pluck` of every active product). `itemLabel` runs `Product::find` per repeater row (N+1). |
| B11 | Low | `PackageResource` (API) | When `valid_from`/`valid_until` are null it emits `""` instead of `null`. `discount_percentage` is an int column but is cast to float. |
| B12 | Low | `packages.description` | Required in the DB and the form. Arabic-only merchants are forced to fill the English field. |
| B13 | Low | `ThemedPackagesSection` | Sorts inactive packages to the end and greys them out, but inactive packages should never reach the storefront at all. |
| B14 | Low | Product `sell_by_weight` | A weight-sold product can be added to a combo, and no weight option exists for it there. |

---

## 2. Business-owner scenarios: the target

A restaurant or shop owner will expect every scenario below. The **Phase** column says where this plan delivers it.

| # | Scenario | Example | Today | Phase |
|---|---|---|---|---|
| S1 | Fixed combo | Burger + fries + Pepsi for 150 | Admin only | 1 |
| S2 | Choose one | "Pick your main: chicken / beef (+20) / fish" | Admin only | 1 |
| S3 | Choose N distinct | "Pick 2 sides" | Admin only | 1 |
| S4 | Choose N **with repeats** | "Box of 6 donuts, any flavours" (3× choc, 3× glazed) | ✗ | 1 |
| S5 | Optional add-on group | "Add a dessert? +25" (min 0) | Partially (min 0 is allowed but untested) | 1 |
| S6 | Premium upcharge | Beef +20 | Data only | 1 |
| S7 | Fixed size | "Medium pizza" (variant pinned) | Data only | 1 |
| S8 | Customer picks size inside combo | "Any pizza, choose size", with upsize charged as a difference | ✗ | 1 |
| S9 | Extras on a component | Extra cheese on the combo burger | ✗ | 2 |
| S10 | Per-component notes | "No onions on the burger" | ✗ | 1 (line note) / 2 (per component) |
| S11 | Family / party packs | 4 burgers + 4 fries + 1L drink | Data only | 1 |
| S12 | Campaign window | Ramadan combos, 1–30 March | Stored, **not enforced** | 0 |
| S13 | Daypart | Lunch combo 12:00–16:00, weekdays only | ✗ | 3 |
| S14 | Branch-specific | Only at the Nasr City branch | ✗ | 3 |
| S15 | Order-type specific | Delivery only / takeaway only | ✗ | 3 |
| S16 | Limits | Max 2 per order; 50 per day | ✗ | 3 |
| S17 | Showcase | Featured, badge, sort, image, strikethrough "you save X" | Partly (no image, savings typed by hand) | 0–1 |
| S18 | No double discounts | "Combos can't be combined with coupons" | ✗ | 2 |
| S19 | Coupon targets combos | "10% off all combos" or "off the Family Box" | ✗ | 2 |
| S20 | Component sold out | Fries unavailable today | Combo still shows | 0 (hide) / 1 (enforce) |
| S21 | Owner edits price while customers have it in cart | Price 150 → 170 | n/a | 1 |
| S22 | Combo expires while in cart | Window closes at 16:00 | n/a | 1 |
| S23 | POS: combo is its own POS product | POS has `COMBO-FAMILY` ref | ✗ | 2 |
| S24 | POS: explode into components | POS only knows burger / fries / soda | ✗ | 2 |
| S25 | Kitchen/staff readability | Order screen shows "Family Box → 2× Beef burger (no onion), 2× Fries…" | ✗ | 1 (admin) / 2 (POS notes) |
| S26 | Reporting | Combo sales; most-picked options | ✗ | 3 |
| S27 | Bilingual AR/EN, RTL | Group and option names in Arabic | Data only | 1 |
| S28 | Guest cart → login merge | Selections survive the cart merge | n/a | 1 |
| S29 | Same combo twice, different picks | Two Family Boxes with different mains | n/a | 1 (separate lines; identical picks merge qty) |
| S30 | Delete/deactivate a product used in combos | Owner removes "Fish" | Silent damage (B1) | 0 |
| S31 | Plugin disabled | Owner turns combo plugin off | n/a | 1 |
| S32 | Order history integrity | Owner edits the combo later; old orders unchanged | n/a | 1 (snapshots) |
| S33 | Duplicate a combo to make a variant | "Copy Family Box → Family Box XL" | ✗ | 1 |
| S34 | Dynamic pricing | "Build your box: sum of picks minus 15%" | ✗ | 4 (optional) |
| S35 | First N free, then pay | "2 sauces free, each extra +5" | ✗ | 4 (optional) |

**Out of scope for this plugin:** BOGO / "buy 2 get 1 free" across normal products, and automatic cart-level
deals. Those belong in a promotions engine (CouponsPromotions), not in combos.

---

## 3. Core design decisions

### D1. A combo is **one cart/order line with component children** (recommended)

The alternative is exploding a combo into N normal product lines. That breaks when the customer removes one
line, it misallocates price, it confuses coupons, and it hides the combo from reports.

- `cart_items` gets `package_id` (nullable FK). `product_id` becomes nullable. A CHECK constraint requires
  exactly one of the two to be set.
- New `cart_item_components` table: `cart_item_id, package_item_id, product_id, variant_id, quantity, price_adjustment, extras(json)`.
- `order_items` gets `package_id` (nullable, `nullOnDelete`). `product_id` becomes nullable.
- New `order_item_components` table: an immutable **snapshot** with `package_item_id` (nullable), `group_name`,
  `group_name_ar`, `product_id`, `variant_id`, `product_name`, `product_name_ar`, `variant_name`, `variant_name_ar`,
  `quantity`, `price_adjustment`, `extras(json)`, `notes`. This covers S32.

### D2. Plugin boundary: CartEngine and OrderManagement must not import ComboPackages

The atlas shows CartEngine and OrderManagement as lower-level plugins. Keep them that way with a
**purchasable line-type contract**:

- CartEngine defines `Contracts\CartLineTypeHandler`, with methods along the lines of `type()`,
  `validate(array $payload)`, `persist(Cart, array $payload)`, `price(CartItem)`, `buildLine(CartItem): CartLine`
  and `revalidate(CartItem): ?string`. Handlers are resolved by container tag (the pattern `tenant.bootstrap.contributors` already uses).
- `CartLine` gets `public string $type = 'product'`, `public ?int $packageId`, and `public array $components = []`
  (`list<CartLineComponent>`).
- OrderManagement's repository writes components from `CartLine::$components`, so it stays unaware of packages.
- PosIntegration's resolver iterates `$item->components` when present.
- ComboPackages registers the `package` handler. `plugin.json` adds `cart-engine` as a dependency.
  Alternatively, combos could degrade to display-only when cart-engine is off; the manifest has no optional
  dependency yet, so pick one.
- **S31:** when combo-packages is inactive, carts drop `package` lines on read (with a notice), and orders still
  render from the snapshots.

### D3. The server is authoritative for selection and price

The client sends only `{package_id, quantity, notes, selections: [{package_item_id, quantity, variant_id?, extras?}]}`.
A `PackageSelectionValidator` in the combo plugin rebuilds everything from the DB:

1. The package is sellable now: `is_active`, inside the validity window, inside the daypart, branch and order type
   allowed (the last three in Phase 3).
2. Each `package_item_id` belongs to this package.
3. `all` groups are filled by the server and the client cannot change them.
4. For each conditional group, the total selected quantity is within [min, max]. If `allow_repeats` is false, each
   option is picked at most once.
5. Every component product is active and every variant is available. A customer-chosen variant (S8) must belong
   to that product.
6. Extras (Phase 2) pass the product's own `ExtrasRule`.
7. Quantity is ≤ `max_per_order` (Phase 3).

**Unit price** = `package.price` + Σ (option `price_adjustment` × picked qty) + Σ variant upsize deltas (S8) + Σ extras (Phase 2).

The same validator runs again at checkout, as an `OrderValidationPipeline` pipe the combo plugin contributes.
That covers S21 and S22. **Recommendation:** if the price changed, reprice the line and show a notice before
payment. If the combo or any picked option is no longer sellable, block checkout for that line and say why.

### D4. POS mapping: support both, package-level mapping wins

- Add `package_id` to `product_pos_mappings`. When a package has a mapping, send one item: `productRef` = the
  package's ref, quantity = line qty, and components written into `notes` (S23).
- Otherwise, explode the components into `posRefObj[]`, with each component's mapped `productRef` and quantity =
  component qty (S24). This is exactly the "meal combo → sandwich + soda" shape in `CREATE_ORDER_API_DOC.md`.
- Any component without a mapping makes `OrderDeliveryEligibility` fail with a clear reason, the same way
  unmapped products fail today.

### D5. Coupons

- Each package gets `allow_coupons` (default **true**). If the cart has any combo with `allow_coupons = false`,
  that line's subtotal is excluded from the coupon base (S18).
- `ApplicableTo` gets `PACKAGES` with `conditions.package_ids`, plus an "all combos" option (S19).
  Product-scoped and category-scoped coupons **do not** match combo components. That keeps the behaviour
  predictable for owners.

---

## 4. Phased plan

### Phase 0: Fix what exists (small, safe, no cart changes)

1. **B1:** change `package_items.product_id` to `restrictOnDelete`. On product delete, Filament shows "used in N
   combos" and blocks the delete. Change `variant_id` to `nullOnDelete` and add a health flag. Add a "used in combos"
   relation manager or badge on the product edit page.
2. **B2/B3/B6:** add a `Package::scopeSellable()` (active + window). Use it in `index()` and `show()` (404
   otherwise). Hide options whose product or variant is unavailable. Hide the whole package if an `all` group
   loses an item, or if a conditional group drops below `min_selections` available options (S20).
3. **B4:** delete `Package::products()` and the `Product::packages` relation. Replace them with
   `Product::packageItems()` through `package_items`.
4. **B5:** rewrite the TS types to mirror the resources exactly, and add a type test or a zod parse in `usePackages`.
5. **B7:** add form validation, either Filament rules plus a `PackageRulesValidator` service called in
   `mutateFormDataBeforeSave`, or the same service reused by the API. Auto-set min = max = 1 for `choose_one`.
6. **B8:** compute the à-la-carte value (default picks, otherwise the cheapest valid picks) live in the form. Make
   `original_price` default to it and derive `discount_percentage`; it becomes read-only, or is dropped from storage
   and computed in the resource. Warn when the combo price is ≥ the à-la-carte value.
7. **B9:** add an `image` column and a FileUpload through the MediaOptimizer plugin's pipeline. Return the URL in the resource.
8. **B10:** use `getSearchResultsUsing`/`getOptionLabelUsing` for the product select, and make `itemLabel` read
   preloaded state.
9. **B11–B14:** null-safe dates, consistent casts, nullable `description`, block `sell_by_weight` products from
   combos (or require a pinned weight value, later).
10. **S33:** add a Duplicate action (deep-copies groups and items). Add bulk activate/deactivate.

**Tests:** sellable-scope matrix (active × window × component availability), product-delete protection,
form-rule validation dataset, resource ↔ TS contract test.

### Phase 1: Make combos purchasable end to end

**Schema** (new tenant migrations; *do not run them against tenants as part of generating the atlas*):

- `package_groups`: add `allow_repeats` (bool, S4), `description`/`description_ar`.
- `package_items`: add `variant_mode` enum `fixed|customer_choice` (S7/S8). With `customer_choice`, the variant's
  price delta over the product's base price is charged on top of `price_adjustment`.
- `packages`: add `allow_coupons`, plus `slug` if storefront URLs need one. Add SoftDeletes so old cart lines
  and orders keep the FK.
- Cart and order tables as in D1.

**Backend:**

- CartEngine: the `CartLineTypeHandler` contract and registry. `CartService::addItem` gains a typed entry point
  (`addLine(type, payload)`), or the existing `store` endpoint accepts `type: "package"`. Calculator, repository,
  `migrateCart` (S28, copies components), and `removePurchasedLines` serialization (it compares `CartLine`
  snapshots, so components must be inside the DTO) all become handler-aware. Lines with identical selections
  merge their quantity (S29).
- ComboPackages: `PackageSelectionValidator`, `PackageLineHandler`, `PackagePriceCalculator`, a checkout pipe for
  `OrderValidationPipeline` (S21/S22).
- OrderManagement: `createOrderWithItems` writes `order_item_components`. The Filament order view shows nested
  components with group labels (S25). Customer order history, tracking and order emails render components.

**Storefront:**

- A package detail route (`/packages/:id`) **and** a quick-configure sheet opened from the card. Both use one
  `PackageConfigurator` component:
  - groups in order; radio for `choose_one`, checkbox for distinct `choose_multiple`, steppers when `allow_repeats`;
  - a live counter ("Choose 2 · 1 selected"), `+20 EGP` tags, a size picker for `customer_choice` items,
    defaults preselected;
  - a sticky footer with live total, quantity, line note, and an Add button disabled until valid. Server errors
    are mapped back onto the group.
- Card: image, badge, original price with strikethrough, "You save X", "Ends in…" for the window, and an
  unavailable state.
- Cart drawer/page and checkout summary: combo line with a collapsible component list, an Edit action (reopens the
  configurator with current picks, then replaces the line), qty, remove. Show the reprice/expired notices from D3.
- i18n: AR/EN for every new string. Use `name_ar`/`description_ar` on groups and options. Check RTL layout.
- Theme contract: add the slots (`combo.configurator`, `combo.card`) and the `data-testid`s to
  `docs/THEME_TEST_IDS_SPEC.md` and `tests/e2e/themes/theme.contract.ts`.
- Analytics: Meta Pixel `AddToCart`/`Purchase` with `content_type: 'product_group'` and the package id
  (align with `docs/META_PIXEL_AND_CAPI_PLAN.md`).

**Tests:**

- Pest, validator: one case per selection type × min/max boundary × repeats × inactive component × foreign
  `package_item_id` × tampered price/`all` group.
- Pest, pricing: adjustments, upsize delta, quantity.
- Pest, cart: add, merge identical, separate on different picks, guest → user sync, plugin deactivated.
- Pest, checkout: reprice notice, expired block, snapshot written, later package edit leaves the order unchanged.
- Playwright live spec: configure → add → edit in cart → checkout (COD) → order page shows components.

### Phase 2: Operations: POS, coupons, extras

- POS mapping per D4, a Filament UI for package POS refs, eligibility messages, and Arabic component notes through
  `PosNoteLabel`. Extend `PlaceOrderPosRefObjContractTest` with combo cases (package-mapped, exploded, missing mapping).
- Coupons per D5: `allow_coupons`, the `PACKAGES` applicability, and the coupon-base exclusion in the pricing
  pipeline. Tests: stacked-discount prevention, package-targeted coupon, product coupon not matching components.
- S9: component extras. Reuse the product's `extraOption` and `ExtrasRule`; per-package-item `allow_extras`.
- S10: per-component notes, shown in the order view and POS notes.

### Phase 3: Merchandising rules and insight

- `package_availability` (days of week + time ranges, tenant timezone) (S13). `package_branch` pivot, where an
  empty pivot means all branches (S14). `allowed_order_types` json (S15). `max_per_order`, `daily_limit` with a
  sold counter that is reserved at order creation and released on cancel/failed payment (S16). Every rule goes
  through `scopeSellable` and the validator, so storefront and checkout always agree.
- Admin table: a health column ("2 options unavailable", "unmapped in POS", "expired"). Filters by health.
- Reporting (S26): a Filament widget for combo revenue and units over a date range, plus option popularity per
  group from `order_item_components`.
- Upsell: "Make it a combo" on the product page when the product appears in a sellable package.

### Phase 4: Advanced pricing (only if owners ask)

- `packages.pricing_mode`: `fixed` (default) or `sum_minus` (sum of picked components − X% or − X EGP) (S34).
- `package_groups.free_selections`: the first N picks are free, each extra is charged its `price_adjustment` or the
  product price (S35).

---

## 5. Cross-cutting requirements for every phase

- **Atlas:** schema, route and plugin-contract changes trigger the AGENTS.md atlas workflow. That means refreshing
  inventories, reviewing `schema-tenant-combopackages`, `routes-combo-packages`, `plugin-dependencies` and the
  cart/order schema diagrams, then running `test_atlas.py` and `atlas.py check .`.
- **Security:** never trust a client price, quantity in an `all` group, or `package_item_id` ownership. Rate-limit
  the cart endpoints like the existing ones. The guest-ordering audit rules (`GUEST_ORDERING_SECURITY_AUDIT.md`)
  apply to package lines.
- **Money:** use decimals end to end. The new columns are `decimal(10,2)`, matching the
  `convert_money_columns_to_decimal` direction.
- **Migrations:** every migration is additive, with nullable FKs and backfill-free defaults, so existing tenants
  upgrade cleanly.

## 5a. Progress

- **Phase 0 — implemented 2026-11-05 (uncommitted).** It was delegated to three parallel agy runs:
  - A: backend/API/delete guard
  - B: Filament admin
  - C: storefront
  
  Each run was reviewed and merged into the main tree.
- Gates in main: 70 combo tests green, 163 product tests green (the only failure is the pre-existing WeightOptionResourceTest), `tsc` clean, atlas rebuilt and `check` passes.
- Not done in Phase 0: S20 runtime enforcement at checkout (Phase 1). `summarizeContents` still hardcodes its EN/AR "Choose N of M" text instead of using i18n keys.
- Migrations `2026_11_05_000001` (image, nullable description) and `2026_11_05_000002` (FK restrict / nullOnDelete) have not been run against any tenant.

- **Phase 1 — implemented 2026-11-06 (uncommitted).** It uses the recommended decisions D1–D5 and was built by four parallel agy runs:
  - P1-A cart-core
  - P1-B orders + POS
  - P1-C combo domain
  - P1-D storefront

  All four were built against a shared foundation I wrote first: `CartLineTypeHandler`, `PreparedCartLine`, `CartLineComponent`, the `CartLine` extensions, the `cart_item_components` table and its model.
  - End-to-end coverage: `tests/Feature/ComboPackages/PurchaseFlowTest.php`. It covers API add → server pricing → merge → rule rejection → live repricing → checkout block → order snapshot.
  - Regression: 895 tests pass across Order/Pos/Coupon/Cart/Combo/Product/Checkout. The 11 failures were all present before Phase 1. `tsc` is clean, and the atlas was rebuilt and passes `check`.
  - Migrations not run against any tenant:
    - `CartEngine 2026_11_06_000001`
    - `OrderManagement 2026_11_06_000002`
    - `ComboPackages 2026_11_06_000003`
  - Known gaps, left for later phases:
    - If the admin adds an item to an `all` group, carts that already hold the combo keep the old contents.
    - Package-level POS mapping is Phase 2; for now components are exploded into `posRefObj`.
    - The `allow_coupons` coupon-base exclusion is Phase 2. The flag is stored and exposed as `CartLine::$couponEligible`, but nothing enforces it yet.
    - No price-change notice is shown in the cart; prices are simply always live.

- **Phase 2 — implemented 2026-11-06 (uncommitted).** Built by four parallel agy runs:
  - P2-A POS + order snapshot
  - P2-B coupons
  - P2-C combo domain + cart persistence
  - P2-D storefront

  All four were built on a foundation I wrote first: the core contracts `FormComponentContributor` and `LineReferenceCatalog`, extras and notes on `CartLineComponent`, and the cart-component migration. PosIntegration and CouponsPromotions still do not depend on ComboPackages.
  - Delivered:
    - S9: component extras, gated by the per-item `allow_extras`.
    - S10: per-component notes.
    - S18: `allow_coupons` is excluded from every coupon base.
    - S19: a "combos" coupon target, optionally limited to specific packages.
    - S23: package-level POS mapping in `pos_line_mappings`, edited from the package form's "تكاملات" tab.
    - S24: exploded combos now carry their extras through the existing pos_item/notes rules.
    - The Phase 1 gap where "all items" contents could change under existing carts is closed.
  - End-to-end coverage: `tests/Feature/Integration/ComboPackagePosFlowTest.php` (cart API → price → order snapshot → both POS shapes) and the extras case in `PurchaseFlowTest`.
  - Regression: 946 tests pass across the Order/Pos/Coupon/Cart/Combo/Product/Checkout/Integration filters. Every remaining failure was present before Phase 2. `tsc` is clean, and the atlas was rebuilt and passes `check`.
  - New migrations, none run against any tenant:
    - CartEngine `2026_11_06_000004`
    - OrderManagement `2026_11_06_000005`
    - PosIntegration `2026_11_06_000006`
    - ComboPackages `2026_11_06_000007`
  - Nit: `PackageSelectionValidator` defaults its `ExtrasRule` dependency (`= new ExtrasRule()`) instead of requiring it. Tests construct it directly.

- **Phase 3 — implemented 2026-11-07 (uncommitted).** Built by four parallel agy runs:
  - P3-A rules
  - P3-B reporting and upsell
  - P3-C POS health column
  - P3-D storefront
  
  The foundation is the core contract `TableColumnContributor`.
  - Delivered:
    - S13 weekly availability windows, including windows that cross midnight.
    - S14 branches.
    - S15 order types.
    - S16 max per order and daily limit. Sold-today is computed on demand; concurrent checkouts can oversell by a small amount, as documented.
    - Health and POS columns.
    - S26 sales and option-popularity widgets.
    - The product-page upsell.
  - My fixes on top:
    - The handler took its quantity from `request()`; CartEngine now passes it in the payload.
    - Option popularity ignored the line quantity.
    - "Available from" parsed UTC as local time.
    - The POS health memo is now partitioned per tenant.
    - Registered the upsell contributor.
  - Regression: 388 tests across combo, cart, POS, orders, coupons, core and architecture pass. Only the 2 known pre-existing architecture failures remain. `tsc` is clean and the atlas passes `check`.
  - Handbook: `docs/features/combo-packages.md` covers phases 0–3. It was restored from the doc session's original after I accidentally overwrote it. `CHANGELOG.md`, `docs/coverage.md`, `docs/data-model.md` and the feature catalog were updated; `docs_check.py` passes.
  - New migration `ComboPackages 2026_11_07_000001`, not run against any tenant.
  - Phase 4 (dynamic pricing modes, "first N free") remains optional.

## 6. Decisions to confirm before Phase 1

1. **D1:** one line with components (recommended) vs. exploded lines.
2. **D3:** on a price change in the cart, reprice and notify (recommended) vs. block.
3. **S20:** when one *option* is unavailable, hide just that option (recommended, when min is still achievable)
   vs. hide the whole combo.
4. **D4:** POS mapping, both with package mapping winning (recommended) vs. explode only.
5. **D5:** default for `allow_coupons` on combos: true (recommended) or false.
6. **D2:** without cart-engine, combos become display-only vs. combo-packages hard-depends on cart-engine.
