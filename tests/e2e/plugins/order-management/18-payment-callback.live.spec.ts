import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';
import { openCheckout } from '../../helpers/checkout';

test('callback checks the server payment status instead of trusting the URL', async ({ page, theme }) => {
  test.setTimeout(90_000);
  await loginAsCustomer(page);
  await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
  await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);
  const [placement] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'),
    theme.orders.getPlaceOrderButton().click(),
  ]);
  expect(placement.status()).toBe(200);
  const orderId = (await placement.json()).data.order_id;
  await expect(page).toHaveURL(new RegExp(`/orders/${orderId}`));

  const paymentStatus = page.waitForResponse(r => new URL(r.url()).pathname === `/api/orders/${orderId}/payment-status`);
  await page.goto(`/orders/${orderId}/payment/callback?status=success`, { waitUntil: 'domcontentloaded' });
  const response = await paymentStatus;
  expect(response.status()).toBe(200);
  const pendingResult = await response.json();
  expect(pendingResult.data.is_paid).toBe(false);
  await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toHaveCount(0);

  // Simulate the server reporting completion; webhook processing is covered by the backend integration test.
  await page.route(`**/api/orders/${orderId}/payment-status`, route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ...pendingResult,
      data: { ...pendingResult.data, is_paid: true, order: { ...pendingResult.data.order, payment_status: 'completed' } },
    }),
  }));
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toBeVisible();
});
