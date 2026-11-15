# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: order-management\20-live-payment.live.spec.ts >> a live Paymob card payment completes the guest order
- Location: tests\e2e\plugins\order-management\20-live-payment.live.spec.ts:8:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('[data-testid="product-detail-add-to-cart"]')
Expected: visible
Timeout: 60000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('[data-testid="product-detail-add-to-cart"]') with timeout 60000ms
  - waiting for locator('[data-testid="product-detail-add-to-cart"]')

```

# Test source

```ts
  1  | import { test, expect } from '../../helpers/live-fixture';
  2  | 
  3  | const baseUrl = process.env.E2E_BASE_URL || 'https://koofe.turbospace.click';
  4  | 
  5  | // A person enters payment details and completes any bank authentication in the gateway.
  6  | test.use({ baseURL: baseUrl});
  7  | 
  8  | test('a live Paymob card payment completes the guest order', async ({ page, theme }) => {
  9  |   test.setTimeout(600_000);
  10 | 
  11 |   const productId = process.env.E2E_LIVE_PRODUCT_ID || '15';
  12 |   const phone = process.env.E2E_LIVE_GUEST_PHONE || '01021153539';
  13 |   expect(new URL(baseUrl).protocol).toBe('https:');
  14 |   expect(productId, 'E2E_LIVE_PRODUCT_ID must be a numeric product ID').toMatch(/^\d+$/);
  15 | 
  16 |   await test.step('Add the selected product to the cart', async () => {
  17 |     await page.goto(`/products/${productId}`, { waitUntil: 'commit' });
> 18 |     await expect(theme.catalog.getDetailAddToCartButton()).toBeVisible({ timeout: 60_000 });
     |                                                            ^ Error: expect(locator).toBeVisible() failed
  19 |     const [cartResponse] = await Promise.all([
  20 |       page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
  21 |       theme.catalog.getDetailAddToCartButton().click(),
  22 |     ]);
  23 |     expect(cartResponse.status()).toBe(201);
  24 |   });
  25 |   await test.step('Complete guest checkout and select card payment', async () => {
  26 |     await page.goto('/checkout', { waitUntil: 'commit' });
  27 |     await expect(theme.orders.getOrderTypeTakeaway()).toBeVisible({ timeout: 90_000 });
  28 |     await theme.orders.getOrderTypeTakeaway().click();
  29 |     await page.getByTestId('guest-name-input').fill('Live Payment Order');
  30 |     await page.getByTestId('guest-phone-input').fill(phone);
  31 |     await page.getByRole('radio', { name: /credit card|بطاقة ائتمان/i }).click();
  32 |   });
  33 | 
  34 |   const placed = await test.step('Place the real order and open Paymob', async () => {
  35 |     const [placement] = await Promise.all([
  36 |       page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST', { timeout: 90_000 }),
  37 |       theme.orders.getPlaceOrderButton().click(),
  38 |     ]);
  39 |     expect(placement.request().postDataJSON().payment_method).toBe('card');
  40 |     expect(placement.status()).toBe(200);
  41 |     const placed = (await placement.json()).data;
  42 |     expect(placed.requires_payment).toBe(true);
  43 |     expect(placed.order_id).toBeTruthy();
  44 |     expect(placed.order_number).toBeTruthy();
  45 |     const checkout = new URL(placed.checkout_url);
  46 |     expect(checkout.protocol).toBe('https:');
  47 |     expect(['accept.paymob.com', 'eg.checkout.paymob.com']).toContain(checkout.hostname);
  48 |     expect(checkout.searchParams.get('publicKey')).toBeTruthy();
  49 |     expect(checkout.searchParams.get('clientSecret')).toBeTruthy();
  50 |     expect(checkout.searchParams.get('publicKey')?.includes('_test_')).toBe(false);
  51 |     expect(checkout.searchParams.get('clientSecret')?.includes('_test_')).toBe(false);
  52 |     await expect.poll(() => new URL(page.url()).hostname, { timeout: 30_000 }).toBe(checkout.hostname);
  53 |     return placed;
  54 |   });
  55 | 
  56 |   // Complete the real card payment and any 3-D Secure challenge in the browser.
  57 |   await test.step('Complete payment in the Paymob browser window', async () => {
  58 |     await page.waitForURL(url => url.origin === new URL(baseUrl).origin &&
  59 |       url.pathname === `/orders/${placed.order_id}/payment/callback`, { timeout: 480_000 });
  60 |   });
  61 |   await test.step('Verify the server confirmed payment and shows the order', async () => {
  62 |     await expect.poll(async () => page.evaluate(async orderId => {
  63 |       const response = await fetch(`/api/orders/${orderId}/payment-status`, { credentials: 'same-origin' });
  64 |       if (!response.ok) return `HTTP ${response.status}`;
  65 |       const body = await response.json();
  66 |       return body.data?.order?.id === orderId && body.data?.is_paid === true &&
  67 |         body.data?.order?.payment_status === 'completed' ? 'completed' : body.data?.order?.payment_status;
  68 |     }, placed.order_id), { timeout: 120_000, intervals: [3000] }).toBe('completed');
  69 |     await page.reload({ waitUntil: 'domcontentloaded' });
  70 |     await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toBeVisible();
  71 |     await expect(page.getByText(`#${placed.order_number}`, { exact: true })).toBeVisible();
  72 |   });
  73 | });
  74 | 
```