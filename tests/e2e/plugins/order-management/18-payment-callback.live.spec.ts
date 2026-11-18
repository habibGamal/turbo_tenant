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

test('guest payment callback View Order navigates to signed track order page', async ({ page, theme }) => {
  test.setTimeout(90_000);
  await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
  await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);
  await theme.orders.getOrderTypeTakeaway().click();
  await page.locator('[data-testid="guest-name-input"], input#guest_name').first().fill('Callback Guest');
  await page.locator('[data-testid="guest-phone-input"], input#guest_phone').first().fill('01012345678');

  const [placement] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'),
    theme.orders.getPlaceOrderButton().click(),
  ]);
  expect(placement.status()).toBe(200);
  const placedData = (await placement.json()).data;
  const orderId = placedData.order_id;
  const orderNumber = placedData.order_number;

  const paymentStatus = page.waitForResponse(r => new URL(r.url()).pathname === `/api/orders/${orderId}/payment-status`);
  await page.goto(`/orders/${orderId}/payment/callback?status=success`, { waitUntil: 'domcontentloaded' });
  const response = await paymentStatus;
  expect(response.status()).toBe(200);
  const statusResult = await response.json();
  expect(statusResult.data.order.is_guest).toBe(true);
  expect(statusResult.data.tracking_token).toBeTruthy();

  await page.route(`**/api/orders/${orderId}/payment-status`, route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ...statusResult,
      data: {
        ...statusResult.data,
        is_paid: true,
        order: { ...statusResult.data.order, payment_status: 'completed' },
      },
    }),
  }));
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toBeVisible();

  const viewOrderLink = page.locator('a', { hasText: /view order|عرض الطلب/i });
  const expectedHref = `/orders/track?order_number=${encodeURIComponent(orderNumber)}&token=${encodeURIComponent(statusResult.data.tracking_token)}`;
  await expect(viewOrderLink).toHaveAttribute('href', expectedHref);

  const [trackResponse] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/guest-checkout/track' && r.request().method() === 'POST'),
    viewOrderLink.click(),
  ]);
  expect(trackResponse.status()).toBe(200);
  await expect(page).toHaveURL(/\/orders\/track/);
  await expect(page.getByText(orderNumber)).toBeVisible();
  await expect(page.getByText(/sign in to view your orders|سجّل الدخول لعرض طلباتك/i)).toHaveCount(0);
});
