import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

// This test creates a real sandbox order and is never enabled in the normal suite.
test.use({ trace: 'off', screenshot: 'off', video: 'off' });

test('Paymob sandbox card payment reaches a verified callback', async ({ page, theme, baseURL }) => {
  test.skip(process.env.E2E_PAYMOB_SANDBOX !== '1', 'Explicit Paymob sandbox opt-in is required.');
  test.setTimeout(180_000);

  const productId = process.env.E2E_PAYMOB_PRODUCT_ID;
  const phone = process.env.E2E_PAYMOB_TEST_PHONE;
  const card = process.env.E2E_PAYMOB_TEST_CARD_NUMBER;
  const expiry = process.env.E2E_PAYMOB_TEST_CARD_EXPIRY;
  const cvv = process.env.E2E_PAYMOB_TEST_CARD_CVV;
  const cardholder = process.env.E2E_PAYMOB_TEST_CARDHOLDER;
  expect(new URL(baseURL ?? '').protocol).toBe('https:');
  expect(Boolean(productId && /^\d+$/.test(productId))).toBe(true);
  expect(Boolean(phone && card && expiry && cvv && cardholder)).toBe(true);

  await page.goto(`/products/${productId}`, { waitUntil: 'domcontentloaded' });
  const [cartResponse] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  expect(cartResponse.status()).toBe(201);
  await openCheckout(page);
  await theme.orders.getOrderTypeTakeaway().click();
  await page.getByTestId('guest-name-input').fill('Paymob Sandbox Test');
  await page.getByTestId('guest-phone-input').fill(phone!);
  await page.getByRole('radio', { name: /credit card|بطاقة ائتمان/i }).click();

  let placed: { requires_payment: boolean; order_id: number } | undefined;
  await page.route('**/api/orders/place', async route => {
    const response = await route.fetch();
    const result = await response.json();
    placed = result.data;
    await route.fulfill({ response });
  });
  const [placement] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'),
    theme.orders.getPlaceOrderButton().click(),
  ]);
  expect(placement.status()).toBe(200);
  expect(placed?.requires_payment).toBe(true);
  expect(placed?.order_id).toBeTruthy();

  await expect.poll(() => new URL(page.url()).hostname, { timeout: 30_000 }).toBe('eg.checkout.paymob.com');
  const hostedCheckout = new URL(page.url());
  expect(hostedCheckout.searchParams.get('clientSecret')?.startsWith('egy_csk_test_')).toBe(true);
  expect(hostedCheckout.searchParams.get('publicKey')?.startsWith('egy_pk_test_')).toBe(true);
  await page.locator('#number').fill(card!);
  await page.locator('#expiry').fill(expiry!);
  await page.getByPlaceholder('CVV').fill(cvv!);
  await page.getByPlaceholder('ex. Abdullah Adel').fill(cardholder!);
  await page.locator('#pay-button').click();

  await expect.poll(() => new URL(page.url()).hostname, { timeout: 90_000 }).toBe(new URL(baseURL!).hostname);
  await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toBeVisible({ timeout: 60_000 });
  const status = await page.evaluate(async orderId => {
    const response = await fetch(`/api/orders/${orderId}/payment-status`, { credentials: 'same-origin' });
    return { status: response.status, body: await response.json() };
  }, placed!.order_id);
  expect(status.status).toBe(200);
  expect(status.body.data.order.id).toBe(placed!.order_id);
  expect(status.body.data.order.payment_status).toBe('completed');
  expect(status.body.data.is_paid).toBe(true);
});
