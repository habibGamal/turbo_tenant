import { test, expect } from '../../helpers/live-fixture';

const baseUrl = process.env.E2E_BASE_URL || 'https://koofe.turbospace.click';

// A person enters payment details and completes any bank authentication in the gateway.
test.use({ baseURL: baseUrl});

test('a live Paymob card payment completes the guest order', async ({ page, theme }) => {
  test.setTimeout(600_000);

  const productId = process.env.E2E_LIVE_PRODUCT_ID || '15';
  const phone = process.env.E2E_LIVE_GUEST_PHONE || '01021153539';
  expect(new URL(baseUrl).protocol).toBe('https:');
  expect(productId, 'E2E_LIVE_PRODUCT_ID must be a numeric product ID').toMatch(/^\d+$/);

  await test.step('Add the selected product to the cart', async () => {
    await page.goto(`/products/${productId}`, { waitUntil: 'commit' });
    await expect(theme.catalog.getDetailAddToCartButton()).toBeVisible({ timeout: 60_000 });
    const [cartResponse] = await Promise.all([
      page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
      theme.catalog.getDetailAddToCartButton().click(),
    ]);
    expect(cartResponse.status()).toBe(201);
  });
  await test.step('Complete guest checkout and select card payment', async () => {
    await page.goto('/checkout', { waitUntil: 'commit' });
    await expect(theme.orders.getOrderTypeTakeaway()).toBeVisible({ timeout: 90_000 });
    await theme.orders.getOrderTypeTakeaway().click();
    await page.getByTestId('guest-name-input').fill('Live Payment Order');
    await page.getByTestId('guest-phone-input').fill(phone);
    await page.getByRole('radio', { name: /credit card|بطاقة ائتمان/i }).click();
  });

  const placed = await test.step('Place the real order and open Paymob', async () => {
    const [placement] = await Promise.all([
      page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST', { timeout: 90_000 }),
      theme.orders.getPlaceOrderButton().click(),
    ]);
    expect(placement.request().postDataJSON().payment_method).toBe('card');
    expect(placement.status()).toBe(200);
    const placed = (await placement.json()).data;
    expect(placed.requires_payment).toBe(true);
    expect(placed.order_id).toBeTruthy();
    expect(placed.order_number).toBeTruthy();
    const checkout = new URL(placed.checkout_url);
    expect(checkout.protocol).toBe('https:');
    expect(['accept.paymob.com', 'eg.checkout.paymob.com']).toContain(checkout.hostname);
    expect(checkout.searchParams.get('publicKey')).toBeTruthy();
    expect(checkout.searchParams.get('clientSecret')).toBeTruthy();
    expect(checkout.searchParams.get('publicKey')?.includes('_test_')).toBe(false);
    expect(checkout.searchParams.get('clientSecret')?.includes('_test_')).toBe(false);
    await expect.poll(() => new URL(page.url()).hostname, { timeout: 30_000 }).toBe(checkout.hostname);
    return placed;
  });

  // Complete the real card payment and any 3-D Secure challenge in the browser.
  await test.step('Complete payment in the Paymob browser window', async () => {
    await page.waitForURL(url => url.origin === new URL(baseUrl).origin &&
      url.pathname === `/orders/${placed.order_id}/payment/callback`, { timeout: 480_000 });
  });
  await test.step('Verify the server confirmed payment and shows the order', async () => {
    await expect.poll(async () => page.evaluate(async orderId => {
      const response = await fetch(`/api/orders/${orderId}/payment-status`, { credentials: 'same-origin' });
      if (!response.ok) return `HTTP ${response.status}`;
      const body = await response.json();
      return body.data?.order?.id === orderId && body.data?.is_paid === true &&
        body.data?.order?.payment_status === 'completed' ? 'completed' : body.data?.order?.payment_status;
    }, placed.order_id), { timeout: 120_000, intervals: [3000] }).toBe('completed');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toBeVisible();
    await expect(page.getByText(`#${placed.order_number}`, { exact: true })).toBeVisible();
  });
});
