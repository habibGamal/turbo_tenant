import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';
import { openCheckout } from '../../helpers/checkout';

test('a lost placement response warns the customer and the committed order is recoverable from history', async ({ page, theme }) => {
  test.setTimeout(90_000);
  await loginAsCustomer(page);
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200),
    page.goto('/products/15', { waitUntil: 'domcontentloaded' }),
  ]);
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);

  let committedOrderNumber = '';
  let placements = 0;
  // Forward the real request, then simulate losing the response after the server commits it.
  await page.route('**/api/orders/place', async route => {
    placements++;
    const originalUrl = new URL(route.request().url());
    const localUrl = new URL(originalUrl);
    if (localUrl.hostname.endsWith('.localhost')) localUrl.hostname = '127.0.0.1';
    const upstream = await route.fetch({
      url: localUrl.toString(),
      headers: { ...route.request().headers(), host: originalUrl.host },
    });
    expect(upstream.status()).toBe(200);
    committedOrderNumber = (await upstream.json()).data.order_number;
    await route.abort('failed');
  });
  await page.getByTestId('place-order-button').click();
  await expect(page.getByText(/could not confirm your order|لم نتمكن من تأكيد طلبك/i)).toBeVisible();
  await expect(page).toHaveURL(/\/checkout/);
  expect(placements).toBe(1);
  expect(committedOrderNumber).toBeTruthy();

  await page.unroute('**/api/orders/place');
  await page.goto('/orders', { waitUntil: 'domcontentloaded' });
  await expect(theme.orders.getOrderCards().filter({ hasText: committedOrderNumber }).first()).toBeVisible();
  expect(placements).toBe(1);
});
