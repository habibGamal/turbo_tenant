# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: catalog-products\09-quick-add-and-modal.live.spec.ts >> CatalogProducts Suite 09: Quick-Add & Options Modal Flow >> detail page add-to-cart button adds configured product to cart
- Location: tests\e2e\plugins\catalog-products\09-quick-add-and-modal.live.spec.ts:59:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('[data-testid="product-detail-add-to-cart"]')
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('[data-testid="product-detail-add-to-cart"]') with timeout 10000ms
  - waiting for locator('[data-testid="product-detail-add-to-cart"]')

```

```yaml
- banner:
  - link "Restaurant":
    - /url: /
  - navigation:
    - link "الرئيسية":
      - /url: /
    - link "القائمة":
      - /url: /menu
    - link "تتبع الطلب":
      - /url: /track-order
  - searchbox "بحث"
  - button "تغيير اللغة"
  - link "View Cart":
    - /url: /cart
    - button "View Cart"
  - button "تسجيل الدخول"
- status "Loading product details":
  - main: / /
- contentinfo:
  - link "Restaurant":
    - /url: /
  - paragraph: Your favorite restaurant
  - heading "روابط سريعة" [level=4]
  - list:
    - listitem:
      - link "الرئيسية":
        - /url: /
    - listitem:
      - link "القائمة":
        - /url: /menu
    - listitem:
      - link "من نحن":
        - /url: /pages/about-us
    - listitem:
      - link "اتصل بنا":
        - /url: /pages/contact-us
  - heading "الدعم" [level=4]
  - list:
    - listitem:
      - link "معلومات التوصيل":
        - /url: /pages/delivery-policy
    - listitem:
      - link "تتبع الطلب":
        - /url: /track-order
    - listitem:
      - link "الإرجاع":
        - /url: /pages/return-policy
  - heading "اتصل بنا" [level=4]
  - list:
    - listitem: 123 Restaurant St, City, State 12345
  - paragraph: © 2026 Restaurant. جميع الحقوق محفوظة..
- region "Notifications alt+T"
```

# Test source

```ts
  1  | import { test, expect } from '../../helpers/live-fixture';
  2  | 
  3  | test.describe('CatalogProducts Suite 09: Quick-Add & Options Modal Flow', () => {
  4  |   test('opens options modal when clicking Add to Cart on product with variants or addons', async ({ page, theme }) => {
  5  |     await page.goto('/', { waitUntil: 'domcontentloaded' });
  6  | 
  7  |     // Product 5 (Simple Coffee) is configured with variants, weights, and addons
  8  |     const productCards = theme.catalog.getProductCards();
  9  |     await expect(productCards.first()).toBeVisible({ timeout: 10000 });
  10 | 
  11 |     // Locate card for Product 5 or first card with options
  12 |     const cardWithOptions = page.locator('[data-testid="product-card"]').filter({
  13 |       hasText: /Simple Coffee|كوف ساده/i,
  14 |     }).first();
  15 | 
  16 |     const targetCard = (await cardWithOptions.count()) > 0 ? cardWithOptions : productCards.first();
  17 |     const addToCartBtn = theme.catalog.getAddToCartButton(targetCard);
  18 |     await expect(addToCartBtn).toBeVisible();
  19 | 
  20 |     // Click Add to Cart button on card - should trigger options modal fetch and open modal
  21 |     await Promise.all([
  22 |       page.waitForResponse((r) => r.url().includes('/api/catalog-products/') && r.status() === 200),
  23 |       addToCartBtn.click(),
  24 |     ]);
  25 | 
  26 |     // Modal should be displayed to choose variants/addons before adding to cart
  27 |     const modal = theme.catalog.getOptionsModal();
  28 |     await expect(modal).toBeVisible({ timeout: 5000 });
  29 | 
  30 |     // Verify modal contains variant options or weight options
  31 |     const variants = theme.catalog.getVariantOptions();
  32 |     if ((await variants.count()) > 0) {
  33 |       await expect(variants.first()).toBeVisible();
  34 |       // Select variant inside modal
  35 |       await variants.nth(1).click();
  36 |     }
  37 | 
  38 |     // Add to cart from inside the modal
  39 |     const modalAddToCart = modal.locator('[data-testid="product-detail-add-to-cart"]');
  40 |     await expect(modalAddToCart).toBeVisible();
  41 | 
  42 |     await Promise.all([
  43 |       page.waitForResponse(
  44 |         (r) => r.url().includes('/api/cart/items') && (r.status() === 201 || r.status() === 200)
  45 |       ),
  46 |       modalAddToCart.click(),
  47 |     ]);
  48 | 
  49 |     // Modal should close upon successful addition
  50 |     await expect(modal).not.toBeVisible({ timeout: 5000 });
  51 | 
  52 |     // Header cart badge increments
  53 |     const badge = theme.cart.getBadgeCount();
  54 |     await expect(badge).toBeVisible({ timeout: 5000 });
  55 |     const badgeText = await badge.textContent();
  56 |     expect(Number(badgeText)).toBeGreaterThanOrEqual(1);
  57 |   });
  58 | 
  59 |   test('detail page add-to-cart button adds configured product to cart', async ({ page, theme }) => {
  60 |     const productPromise = page.waitForResponse(
  61 |       (r) => r.url().includes('/api/catalog-products/15') && r.status() === 200
  62 |     ).catch(() => null);
  63 |     await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
  64 |     await productPromise;
  65 | 
  66 |     const addToCartButton = theme.catalog.getDetailAddToCartButton();
> 67 |     await expect(addToCartButton).toBeVisible({ timeout: 10000 });
     |                                   ^ Error: expect(locator).toBeVisible() failed
  68 | 
  69 |     const [response] = await Promise.all([
  70 |       page.waitForResponse(
  71 |         (r) => r.url().includes('/api/cart/items') && (r.status() === 201 || r.status() === 200)
  72 |       ),
  73 |       addToCartButton.click(),
  74 |     ]);
  75 | 
  76 |     expect(response.ok()).toBeTruthy();
  77 | 
  78 |     const badge = theme.cart.getBadgeCount();
  79 |     await expect(badge).toBeVisible({ timeout: 5000 });
  80 |   });
  81 | });
  82 | 
```