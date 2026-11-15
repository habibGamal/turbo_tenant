import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

test('an offline checkout attempt keeps the cart and can be retried after reconnection', async ({ page, context, theme }) => {
  test.setTimeout(90_000);
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200),
    page.goto('/products/15', { waitUntil: 'domcontentloaded' }),
  ]);
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);
  await theme.orders.getOrderTypeTakeaway().click();
  await page.getByTestId('guest-name-input').fill('E2E Offline Guest');
  await page.getByTestId('guest-phone-input').fill('01077776666');

  // Going offline before submission makes this a known non-delivery, not an ambiguous post-commit failure.
  await context.setOffline(true);
  try {
    await page.getByTestId('place-order-button').click();
    await expect(page.locator('[data-sonner-toast][data-type="error"]').first()).toBeVisible();
    await expect(page).toHaveURL(/\/checkout/);
    await expect(theme.orders.getOrderSummary()).toContainText('E2E Espresso');
  } finally {
    await context.setOffline(false);
  }

  const [retry] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'),
    page.getByTestId('place-order-button').click(),
  ]);
  expect(retry.status()).toBe(200);
  const result = await retry.json();
  expect(result.status).toBe('success');
  await expect(page).toHaveURL(/\/orders\/track/);
  expect(new URL(page.url()).searchParams.get('order_number')).toBe(result.data.order_number);
});
