# Combo packages

## Overview

Meal bundles sold at a package price. A package has groups of items:
- **Fixed items** (`all`).
- **Choose exactly one** (`choose_one`).
- **Choose between a minimum and maximum** (`choose_multiple`), optionally allowing the same option more than once.

Items can carry a price adjustment, let the customer pick a size, and allow add-ons and a short per-item note. Packages can be limited to certain hours, branches and order types, and have per-order and daily limits.

**Status:** live · **Plugin:** `combo-packages` ([plugin.json](../../app/Plugins/ComboPackages/plugin.json)) · **Depends on:** catalog-products, cart-engine, order-management, locations-delivery · **Related:** [Catalog](catalog.md), [Cart](cart.md), [Coupons](coupons.md), [POS integration](pos-integration.md)

## How to use

### Store admin: build a package

**العروض والخصومات › باقات التوفير** (Offers & discounts › Saving packages):

1. Package details:
   - **صورة الباقة** (image), **الاسم / الاسم (عربي)**. At least one name is required; an Arabic-only name is also stored as the English name.
   - **الوصف**, **سعر الباقة** (package price).
   - **السعر الأصلي** and **نسبة الخصم**, shown to customers as savings. They are filled in from **القيمة الإفرادية المقدرة**, the estimated à-la-carte value.
   - **صالح من / صالح حتى** (validity window), **نشط** (active), **مميز** (featured).
   - **السماح باستخدام كوبونات الخصم** (allow coupons).
   - Badge, icon, card gradient, display order.
2. Groups: **اسم المجموعة** (name, EN/AR), description, selection type, min/max selections, allow repeats.
3. Items per group:
   - product, with either a fixed variant or "customer choice" of variant (a pricier size is charged as the difference);
   - quantity, price adjustment, default item;
   - **السماح بالإضافات** (allow the product's own add-ons).
4. **قواعد البيع** (sales rules) tab:
   - weekly availability windows, which may cross midnight; no windows means always available;
   - branches (none selected means all branches);
   - order types (توصيل / استلام, i.e. delivery / pickup);
   - maximum per order and daily limit.
5. Saving validates the rules with [`PackageRulesValidator`](../../app/Plugins/ComboPackages/src/Services/PackageRulesValidator.php). For example: `choose_one` has min = max = 1; for `choose_multiple`, max ≤ the number of items unless repeats are allowed; defaults stay within limits; there are no duplicate options or sell-by-weight products; availability windows don't overlap; limits are ≥ 1.
6. With POS active, the **تكاملات** tab has a POS mapping section: one POS product for the whole package, per branch or for all branches.

Other admin tools:
- **Product deletion:** a product used in any package cannot be deleted. The admin shows which packages use it.
- **List page:** widgets for units sold, revenue, average price and share of revenue, plus a top-packages table and per-group option popularity.
- **Table actions:** duplicate a package, bulk activate/deactivate.
- **Health column:**
  - **الحالة** (status), one of: غير نشطة / منتهية / مجدولة / غير مكتملة / نفدت اليوم / خارج أوقات التوفر / متاحة;
  - a POS column (with POS active): مربوطة / مكونات مربوطة / غير مربوطة.

### Customer

1. Packages appear on the home page and at `/packages/:id`. Cards show savings, contents, availability hours, "delivery only" / "pickup only", and "only N left today" / "sold out today". A product page lists up to three packages containing that product ("اجعلها وجبة").
2. For each group, pick the required options ("You must choose exactly one option for this group." / "You must choose at least N option(s)…" guide you). Choose sizes, add-ons and a note where allowed, then **Add to Cart**. Outside the package's hours, or when it is sold out, the button is disabled and the reason is shown.
3. The cart shows the package as one line, listing its components, add-ons and notes. Identical packages merge into one line; the line can be edited or removed.
4. At checkout the package is re-checked: hours, branch, order type, limits and current contents. A failure names the package.

## How it works

1. **Catalog:** [`PackageApiController`](../../app/Plugins/ComboPackages/src/Http/Controllers/Api/PackageApiController.php) lists sellable packages. Its optional `?product_id=` parameter limits the list to packages containing that product.
   - Sellable means active, inside the validity window, and with every referenced product usable ([`SellablePackageFilter`](../../app/Plugins/ComboPackages/src/Services/SellablePackageFilter.php)).
   - [`PackageAvailabilityService`](../../app/Plugins/ComboPackages/src/Services/PackageAvailabilityService.php) adds `available_now`, `unavailable_reason`, `next_available_at` and `remaining_today`. It uses the app timezone, the same clock as store hours.
   - [`StorefrontBootstrapContributor`](../../app/Plugins/ComboPackages/src/Bootstrap/StorefrontBootstrapContributor.php) prefetches packages.
   - [`ProductComboUpsellContributor`](../../app/Plugins/ComboPackages/src/Catalog/ProductComboUpsellContributor.php) adds `combo_packages` to product details.
2. **Cart line type:**
   - [`PackageCartLineHandler`](../../app/Plugins/ComboPackages/src/Cart/PackageCartLineHandler.php) is tagged `cart-engine.line-types`.
   - `POST /api/cart/items` with `type: "package"` is validated by [`PackageSelectionValidator`](../../app/Plugins/ComboPackages/src/Services/PackageSelectionValidator.php). `all` items are added by the server; client selections for them only add extras or notes.
   - The selection is stored as a cart line with components. Adding is refused outside hours, and over the per-order or remaining daily quantity.
3. **Price:** calculated by [`PackageLinePricer`](../../app/Plugins/ComboPackages/src/Services/PackageLinePricer.php):
   - unit price = package price + Σ (component price adjustment + positive variant price difference when the customer chose a pricier size) × selection quantity + add-ons;
   - it is recomputed from the live catalog on every cart read;
   - the line carries `couponEligible = allow_coupons`.
4. **Checkout:** [`ValidatePackageLinesPipe`](../../app/Plugins/ComboPackages/src/Pipelines/ValidatePackageLinesPipe.php) (priority 210) re-checks package lines:
   - sellability, and that the selection is still valid;
   - that the fixed contents are unchanged;
   - hours, branch and order type;
   - the per-order total across lines, and the daily limit.
   Order items keep a snapshot of their components (`order_item_components`), including names, add-ons and notes.
5. **Daily limit:** sold-today is counted on demand from today's package order lines, excluding cancelled and failed-payment orders. Two concurrent checkouts can both take the last unit, because there is no reservation or lock.
6. **POS:** a package mapped in `pos_line_mappings` is sent as one POS product, with the components in the notes. Otherwise each component is sent as its own `posRefObj` entry, and add-ons follow the product `pos_item`/notes rules. See [POS integration](pos-integration.md).
7. **Coupons:** product- and category-scoped coupons never apply to packages. A coupon can target packages (all or chosen) through [`PackageLineReferenceCatalog`](../../app/Plugins/ComboPackages/src/Catalog/PackageLineReferenceCatalog.php). See [Coupons](coupons.md).
8. **Protection:** [`PackageProductDeletionGuard`](../../app/Plugins/ComboPackages/src/Guards/PackageProductDeletionGuard.php) blocks deleting products used in packages.
9. **Cross-plugin admin UI:** POS adds its form section and table column through the core contracts `FormComponentContributor` and `TableColumnContributor`, without depending on this plugin. The POS column reads `package_groups` / `package_items` read-only.

### Data

| Owner | Tables and columns |
| --- | --- |
| This plugin | `packages` (incl. `allow_coupons`, `allowed_order_types`, `max_per_order`, `daily_limit`, soft deletes), `package_groups`, `package_items` (incl. `variant_mode`, `allow_extras`), `package_availabilities`, `package_branch` |
| Cart | `cart_items.line_type` / `line_reference_id`, and `cart_item_components` |
| Orders | `order_items.line_type` / `line_reference_id`, and `order_item_components` |
| POS | `pos_line_mappings` |

## Configuration

No store settings. Activate after catalog-products, cart-engine, order-management and locations-delivery. [`PackageSeeder`](../../database/seeders/PackageSeeder.php) provides sample packages for local seeding.

New tenant migrations:
- this plugin: `2026_11_05_000001`, `2026_11_05_000002`, `2026_11_06_000003`, `2026_11_06_000007`, `2026_11_07_000001`
- CartEngine: `2026_11_06_000001`, `2026_11_06_000004`
- OrderManagement: `2026_11_06_000002`, `2026_11_06_000005`
- PosIntegration: `2026_11_06_000006`

None of them has been run against a tenant or MySQL during development. Run them per tenant, in dependency order (see [deployment](../deployment.md)).

**Rollback caveats:**
- Once package lines exist, rolling back the cart/order migrations fails, because their `down()` restores NOT NULL `product_id`.
- Rolling back `2026_11_05_000002` restores the cascade that silently removed products from packages.

## Testing

- **Automated:**
  - [tests/Feature/ComboPackages](../../tests/Feature/ComboPackages): selection validator, pricer, cart handler, sales rules (availability, sold today, add-to-cart, checkout), reporting, upsell, purchase flow, admin rules/estimator.
  - [ComboPackagesTest](../../tests/Feature/ComboPackagesTest.php).
  - [ComboPackagePosFlowTest](../../tests/Feature/Integration/ComboPackagePosFlowTest.php): cart API to order snapshot to both POS payload shapes.
- **Browser:** [combo-packages.live.spec.ts](../../tests/e2e/plugins/combo-packages.live.spec.ts) and [combo-packages-purchase.live.spec.ts](../../tests/e2e/plugins/combo-packages-purchase.live.spec.ts). Neither has been run since these changes.
- **Not verified:** rendered UI and RTL layout, live POS behaviour, MySQL migration execution.

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Package not shown | Inactive, outside validity window, or a referenced product/variant unavailable | Check the package's الحالة column, then the products |
| Shown but cannot be added | Outside availability hours, sold out today, or over the per-order limit | Check قواعد البيع |
| Checkout says the package is unavailable for the branch or order type | Branch or order-type restriction | Adjust قواعد البيع or choose another branch/type |
| "Package contents have changed" at checkout | Fixed (`all`) items were edited after the customer added the package | Customer removes and re-adds it |
| Package line disappears from the cart | Package row deleted, or the combo plugin deactivated | Expected; the line is dropped and logged |
| Cannot save package | Group rules invalid | Follow the validation message |
| Coupon ignores the package | `allow_coupons` off, or the coupon is product/category-scoped | Enable on the package or target packages in the coupon |
| POS rejects the order | A component product is not mapped and the package has no package-level mapping | Map the package in تكاملات, or map the components |

## History

- 2026-11: packages become purchasable end to end:
  - customer choices, sizes, add-ons and notes; server pricing; order snapshots;
  - POS package or component mapping; coupon targeting;
  - sales rules (hours, branches, order types, limits); admin health, reporting and upsell.
- 2026-10: packages as composite cart lines, order components, POS mapping.
- 2025-11: initial packages.
