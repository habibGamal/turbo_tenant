# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: cart-engine\01-add-to-cart.live.spec.ts >> CartEngine Suite 01: Add to Cart & Badge Sync >> adds standard product to cart and increments header badge
- Location: tests\e2e\plugins\cart-engine\01-add-to-cart.live.spec.ts:4:3

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
        - link "Larament" [ref=e7] [cursor=pointer]:
          - /url: /
        - navigation [ref=e13]:
          - link [ref=e14] [cursor=pointer]:
            - /url: /
            - button "الرئيسية" [ref=e15]
          - link [ref=e16] [cursor=pointer]:
            - /url: /menu
            - button "القائمة" [ref=e17]
          - link [ref=e18] [cursor=pointer]:
            - /url: /track-order
            - button "تتبع الطلب" [ref=e19]
        - searchbox "بحث" [ref=e22]
        - generic [ref=e23]:
          - button "تغيير اللغة" [ref=e24]
          - link [ref=e27] [cursor=pointer]:
            - /url: /cart
            - button "View Cart" [ref=e28]
          - button "تسجيل الدخول" [ref=e29]
    - generic [ref=e33]:
      - tablist [ref=e36]:
        - tab "E2E Turkish Coffee" [ref=e37]
        - tab "E2E Espresso" [ref=e38]
        - tab "E2E Coffee Tools" [ref=e39]
      - generic [ref=e41]:
        - generic [ref=e42]:
          - heading "E2E Featured" [level=2] [ref=e49]
          - link [ref=e50] [cursor=pointer]:
            - /url: /sections/2
            - button "عرض الكل" [ref=e51]
        - generic [ref=e52]:
          - generic [ref=e53]:
            - generic [ref=e54]:
              - link [aria-hidden] [ref=e55] [cursor=pointer]:
                - /url: /products/15
                - generic [ref=e56]: DEV MODE
              - button "إضافة إلى المفضلة" [ref=e57]
            - generic [ref=e58]:
              - paragraph [ref=e59]: E2E Espresso
              - heading [level=3] [ref=e60]:
                - link "E2E Espresso" [ref=e61] [cursor=pointer]:
                  - /url: /products/15
            - generic [ref=e62]:
              - paragraph [ref=e64]: E2E test product
              - generic [ref=e65]:
                - generic [ref=e66]: ‏166 ج.م.‏
                - button "أضف إلى السلة" [ref=e69]
          - generic [ref=e70]:
            - generic [ref=e71]:
              - link [aria-hidden] [ref=e72] [cursor=pointer]:
                - /url: /products/16
                - generic [ref=e73]: DEV MODE
              - button "إضافة إلى المفضلة" [ref=e74]
            - generic [ref=e75]:
              - paragraph [ref=e76]: E2E Espresso
              - heading [level=3] [ref=e77]:
                - link "E2E Latte" [ref=e78] [cursor=pointer]:
                  - /url: /products/16
            - generic [ref=e79]:
              - paragraph [ref=e81]: E2E test product
              - generic [ref=e82]:
                - generic [ref=e83]: ‏135 ج.م.‏
                - button "أضف إلى السلة" [ref=e86]
          - generic [ref=e87]:
            - generic [ref=e88]:
              - link [aria-hidden] [ref=e89] [cursor=pointer]:
                - /url: /products/5
                - generic [ref=e90]: DEV MODE
              - button "إضافة إلى المفضلة" [ref=e91]
            - generic [ref=e92]:
              - paragraph [ref=e93]: E2E Turkish Coffee
              - heading [level=3] [ref=e94]:
                - link "E2E Simple Coffee" [ref=e95] [cursor=pointer]:
                  - /url: /products/5
            - generic [ref=e96]:
              - paragraph [ref=e98]: E2E test product
              - generic [ref=e99]:
                - generic [ref=e100]:
                  - generic [ref=e101]: ‏600 ج.م.‏
                  - paragraph [ref=e103]: لكل كجم
                - button "أضف إلى السلة" [ref=e104]
    - contentinfo [ref=e105]:
      - generic [ref=e106]:
        - generic [ref=e107]:
          - generic [ref=e108]:
            - link "Larament" [ref=e109] [cursor=pointer]:
              - /url: /
            - paragraph [ref=e115]: Your favorite restaurant
          - generic [ref=e116]:
            - heading "روابط سريعة" [level=4] [ref=e117]
            - list [ref=e118]:
              - listitem [ref=e119]:
                - link "الرئيسية" [ref=e120] [cursor=pointer]:
                  - /url: /
              - listitem [ref=e121]:
                - link "القائمة" [ref=e122] [cursor=pointer]:
                  - /url: /menu
              - listitem [ref=e123]:
                - link "من نحن" [ref=e124] [cursor=pointer]:
                  - /url: /pages/about-us
              - listitem [ref=e125]:
                - link "اتصل بنا" [ref=e126] [cursor=pointer]:
                  - /url: /pages/contact-us
          - generic [ref=e127]:
            - heading "الدعم" [level=4] [ref=e128]
            - list [ref=e129]:
              - listitem [ref=e130]:
                - link "معلومات التوصيل" [ref=e131] [cursor=pointer]:
                  - /url: /pages/delivery-policy
              - listitem [ref=e132]:
                - link "تتبع الطلب" [ref=e133] [cursor=pointer]:
                  - /url: /orders
              - listitem [ref=e134]:
                - link "الإرجاع" [ref=e135] [cursor=pointer]:
                  - /url: /pages/return-policy
          - generic [ref=e136]:
            - heading "سياساتنا" [level=4] [ref=e137]
            - list
          - generic [ref=e138]:
            - heading "اتصل بنا" [level=4] [ref=e139]
            - list [ref=e140]:
              - listitem [ref=e141]:
                - generic [ref=e145]: 123 Restaurant St, City, State 12345
        - paragraph [ref=e147]: © 2026 Larament. جميع الحقوق محفوظة..
  - region "Notifications alt+T"
```

# Test source

```ts
  1  | import { test, expect } from '../../helpers/live-fixture';
  2  | 
  3  | test.describe('CartEngine Suite 01: Add to Cart & Badge Sync', () => {
  4  |   test('adds standard product to cart and increments header badge', async ({ page, theme }) => {
  5  |     await page.goto('/', { waitUntil: 'domcontentloaded' });
  6  | 
  7  |     const activeAddButtons = page.locator('[data-testid="product-add-to-cart"]:not([disabled])');
  8  |     await expect(activeAddButtons.first()).toBeVisible({ timeout: 10000 });
  9  | 
  10 |     // Click Add to Cart on catalog card
  11 |     const [response] = await Promise.all([
> 12 |       page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
     |            ^ TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
  13 |       activeAddButtons.first().click(),
  14 |     ]);
  15 |     expect(response.ok()).toBeTruthy();
  16 | 
  17 |     // Verify header cart badge updates to 1
  18 |     const badge = theme.cart.getBadgeCount();
  19 |     await expect(badge).toBeVisible({ timeout: 5000 });
  20 |     await expect(badge).toHaveText('1');
  21 |   });
  22 | 
  23 |   test('adds multiple distinct products and synchronizes badge count', async ({ page, theme }) => {
  24 |     await page.goto('/', { waitUntil: 'domcontentloaded' });
  25 | 
  26 |     const activeAddButtons = page.locator('[data-testid="product-add-to-cart"]:not([disabled])');
  27 |     await expect(activeAddButtons.first()).toBeVisible({ timeout: 10000 });
  28 |     const count = await activeAddButtons.count();
  29 |     test.skip(count < 2, 'Need at least 2 active products to test distinct additions');
  30 | 
  31 |     // Add First Product
  32 |     await Promise.all([
  33 |       page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
  34 |       activeAddButtons.nth(0).click(),
  35 |     ]);
  36 | 
  37 |     const badge = theme.cart.getBadgeCount();
  38 |     await expect(badge).toBeVisible({ timeout: 5000 });
  39 |     await expect(badge).toHaveText('1');
  40 | 
  41 |     // Add Second Product
  42 |     await Promise.all([
  43 |       page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
  44 |       activeAddButtons.nth(1).click(),
  45 |     ]);
  46 | 
  47 |     // Verify header cart badge updates to 2
  48 |     await expect(badge).toHaveText('2', { timeout: 5000 });
  49 | 
  50 |     // Navigate to /cart and verify both line items exist
  51 |     await theme.cart.open();
  52 |     await expect(page).toHaveURL(/\/cart/);
  53 |     const items = theme.cart.getItems();
  54 |     await expect(items).toHaveCount(2);
  55 |   });
  56 | });
  57 | 
```