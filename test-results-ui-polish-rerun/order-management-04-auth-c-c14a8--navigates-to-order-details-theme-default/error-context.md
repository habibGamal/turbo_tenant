# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: order-management\04-auth-customer-order.live.spec.ts >> OrderManagement Suite 04: Authenticated Customer Order Placement >> places delivery order as logged-in customer and navigates to order details
- Location: tests\e2e\plugins\order-management\04-auth-customer-order.live.spec.ts:6:3

# Error details

```
Test timeout of 45000ms exceeded.
```

```
Error: expect(locator).toBeVisible() failed

Locator: locator('[data-testid="order-details-container"]')
Expected: visible
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('[data-testid="order-details-container"]') with timeout 10000ms
  - waiting for locator('[data-testid="order-details-container"]')
  - Test timeout of 45000ms exceeded.

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
  - searchbox "بحث"
  - button "تغيير اللغة"
  - button "الإشعارات"
  - link "View Cart":
    - /url: /cart
    - button "View Cart"
  - button "قائمة المستخدم": E
- status "Loading order details"
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
        - /url: /orders
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
  2  | import { openCheckout } from '../../helpers/checkout';
  3  | import { loginAsCustomer } from '../../helpers/auth';
  4  | 
  5  | test.describe('OrderManagement Suite 04: Authenticated Customer Order Placement', () => {
  6  |   test('places delivery order as logged-in customer and navigates to order details', async ({ page, theme }) => {
  7  |     // 1. Authenticate as seeded customer
  8  |     await loginAsCustomer(page, 'admin@example.com', 'password');
  9  | 
  10 |     // 2. Add product to cart
  11 |     await Promise.all([
  12 |       page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200),
  13 |       page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
  14 |     ]);
  15 | 
  16 |     const addBtn = theme.catalog.getDetailAddToCartButton();
  17 |     await Promise.all([
  18 |       page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
  19 |       addBtn.click(),
  20 |     ]);
  21 | 
  22 |     // 3. Go to checkout
  23 |     const { cart, locations } = await openCheckout(page);
  24 | 
  25 |     expect(cart.items.length).toBeGreaterThan(0);
  26 |     expect(locations.addresses.length).toBeGreaterThan(0);
  27 | 
  28 |     // Verify guest form is not rendered for authenticated customer
  29 |     await expect(page.locator('[data-testid="guest-name-input"]')).not.toBeVisible();
  30 | 
  31 |     // 4. Submit order
  32 |     const placeOrderBtn = theme.orders.getPlaceOrderButton();
  33 |     const [placeOrderResponse] = await Promise.all([
  34 |       page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
  35 |       placeOrderBtn.click(),
  36 |     ]);
  37 | 
  38 |     const result = await placeOrderResponse.json();
  39 |     expect(result.status).toBe('success');
  40 |     expect(result.data.order_id).toBeDefined();
  41 |     expect(result.data.requires_payment).toBe(false);
  42 |     expect(result.data.tracking_token).toBeUndefined();
  43 | 
  44 |     // 5. Verify navigation to Order Details view
  45 |     await expect(page).toHaveURL(new RegExp(`/orders/${result.data.order_id}`));
  46 |     const detailsContainer = theme.orders.getOrderDetailsContainer();
> 47 |     await expect(detailsContainer).toBeVisible({ timeout: 10000 });
     |                                    ^ Error: expect(locator).toBeVisible() failed
  48 | 
  49 |     // 6. Verify order number displayed matches returned order
  50 |     const orderNumberEl = theme.orders.getOrderNumber(detailsContainer);
  51 |     await expect(orderNumberEl).toBeVisible();
  52 |     await expect(orderNumberEl).toContainText(String(result.data.order_number || result.data.order_id));
  53 |   });
  54 | });
  55 | 
```