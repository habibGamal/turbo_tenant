# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: catalog-products\09-quick-add-and-modal.live.spec.ts >> CatalogProducts Suite 09: Quick-Add & Options Modal Flow >> opens options modal when clicking Add to Cart on product with variants or addons
- Location: tests\e2e\plugins\catalog-products\09-quick-add-and-modal.live.spec.ts:4:3

# Error details

```
TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
```

# Page snapshot

```yaml
- generic [ref=e2]:
  - generic [ref=e3]:
    - banner [ref=e4]:
      - generic [ref=e6]:
        - link "Restaurant" [ref=e7] [cursor=pointer]:
          - /url: /
        - navigation [ref=e13]:
          - link "الرئيسية" [ref=e14] [cursor=pointer]:
            - /url: /
          - link "القائمة" [ref=e15] [cursor=pointer]:
            - /url: /menu
          - link "تتبع الطلب" [ref=e16] [cursor=pointer]:
            - /url: /track-order
        - searchbox "بحث" [ref=e19]
        - generic [ref=e20]:
          - button "تغيير اللغة" [ref=e21]
          - link [ref=e24] [cursor=pointer]:
            - /url: /cart
            - button "View Cart" [ref=e25]
          - button "تسجيل الدخول" [ref=e26]
    - generic [ref=e30]:
      - tablist [ref=e33]:
        - tab "DEV MODE E2E Turkish Coffee" [ref=e34]:
          - generic [ref=e35]: DEV MODE
          - generic [ref=e37]: E2E Turkish Coffee
        - tab "DEV MODE E2E Espresso" [ref=e38]:
          - generic [ref=e39]: DEV MODE
          - generic [ref=e41]: E2E Espresso
        - tab "DEV MODE E2E Coffee Tools" [ref=e42]:
          - generic [ref=e43]: DEV MODE
          - generic [ref=e45]: E2E Coffee Tools
      - generic [ref=e47]:
        - generic [ref=e48]:
          - heading "E2E Featured" [level=2] [ref=e55]
          - link "عرض الكل" [ref=e56] [cursor=pointer]:
            - /url: /menu
        - generic [ref=e57]:
          - generic [ref=e58]:
            - generic [ref=e59]:
              - link [aria-hidden] [ref=e60] [cursor=pointer]:
                - /url: /products/15
                - generic [ref=e61]: DEV MODE
              - button "إضافة إلى المفضلة" [ref=e62]
            - generic [ref=e63]:
              - paragraph [ref=e64]: E2E Espresso
              - heading [level=3] [ref=e65]:
                - link "E2E Espresso" [ref=e66] [cursor=pointer]:
                  - /url: /products/15
            - generic [ref=e67]:
              - paragraph [ref=e69]: E2E test product
              - generic [ref=e70]:
                - generic [ref=e71]: ‏166 ج.م.‏
                - button "أضف إلى السلة" [ref=e74]
          - generic [ref=e75]:
            - generic [ref=e76]:
              - link [aria-hidden] [ref=e77] [cursor=pointer]:
                - /url: /products/16
                - generic [ref=e78]: DEV MODE
              - button "إضافة إلى المفضلة" [ref=e79]
            - generic [ref=e80]:
              - paragraph [ref=e81]: E2E Espresso
              - heading [level=3] [ref=e82]:
                - link "E2E Latte" [ref=e83] [cursor=pointer]:
                  - /url: /products/16
            - generic [ref=e84]:
              - paragraph [ref=e86]: E2E test product
              - generic [ref=e87]:
                - generic [ref=e88]: ‏135 ج.م.‏
                - button "أضف إلى السلة" [ref=e91]
          - generic [ref=e92]:
            - generic [ref=e93]:
              - link [aria-hidden] [ref=e94] [cursor=pointer]:
                - /url: /products/5
                - generic [ref=e95]: DEV MODE
              - button "إضافة إلى المفضلة" [ref=e96]
            - generic [ref=e97]:
              - paragraph [ref=e98]: E2E Turkish Coffee
              - heading [level=3] [ref=e99]:
                - link "E2E Simple Coffee" [ref=e100] [cursor=pointer]:
                  - /url: /products/5
            - generic [ref=e101]:
              - paragraph [ref=e103]: E2E test product
              - generic [ref=e104]:
                - generic [ref=e105]:
                  - generic [ref=e106]: ‏600 ج.م.‏
                  - paragraph [ref=e108]: لكل كجم
                - button "أضف إلى السلة" [disabled]
    - contentinfo [ref=e109]:
      - generic [ref=e110]:
        - generic [ref=e111]:
          - generic [ref=e112]:
            - link "Restaurant" [ref=e113] [cursor=pointer]:
              - /url: /
            - paragraph [ref=e119]: Your favorite restaurant
          - generic [ref=e120]:
            - heading "روابط سريعة" [level=4] [ref=e121]
            - list [ref=e122]:
              - listitem [ref=e123]:
                - link "الرئيسية" [ref=e124] [cursor=pointer]:
                  - /url: /
              - listitem [ref=e125]:
                - link "القائمة" [ref=e126] [cursor=pointer]:
                  - /url: /menu
              - listitem [ref=e127]:
                - link "من نحن" [ref=e128] [cursor=pointer]:
                  - /url: /pages/about-us
              - listitem [ref=e129]:
                - link "اتصل بنا" [ref=e130] [cursor=pointer]:
                  - /url: /pages/contact-us
          - generic [ref=e131]:
            - heading "الدعم" [level=4] [ref=e132]
            - list [ref=e133]:
              - listitem [ref=e134]:
                - link "معلومات التوصيل" [ref=e135] [cursor=pointer]:
                  - /url: /pages/delivery-policy
              - listitem [ref=e136]:
                - link "تتبع الطلب" [ref=e137] [cursor=pointer]:
                  - /url: /track-order
              - listitem [ref=e138]:
                - link "الإرجاع" [ref=e139] [cursor=pointer]:
                  - /url: /pages/return-policy
          - generic [ref=e140]:
            - heading "اتصل بنا" [level=4] [ref=e141]
            - list [ref=e142]:
              - listitem [ref=e143]:
                - generic [ref=e147]: 123 Restaurant St, City, State 12345
        - paragraph [ref=e149]: © 2026 Restaurant. جميع الحقوق محفوظة..
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
> 22 |       page.waitForResponse((r) => r.url().includes('/api/catalog-products/') && r.status() === 200),
     |            ^ TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
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
  67 |     await expect(addToCartButton).toBeVisible({ timeout: 10000 });
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