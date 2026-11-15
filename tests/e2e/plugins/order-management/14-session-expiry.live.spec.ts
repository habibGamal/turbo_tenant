import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';
import { openCheckout } from '../../helpers/checkout';

test('a customer whose session expires is sent to sign in before ordering', async ({ page, context, theme }) => {
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
  await expect(page.getByTestId('guest-name-input')).toHaveCount(0);
  // Simulate expiration after the authenticated checkout has already rendered.
  await context.clearCookies();
  let placements = 0;
  page.on('request', request => {
    if (new URL(request.url()).pathname === '/api/orders/place' && request.method() === 'POST') placements++;
  });
  await page.getByTestId('place-order-button').click();
  await expect(page).toHaveURL(/\/login\?redirect=%2Fcheckout/);
  await expect(page.getByText(/your session expired|انتهت جلستك/i)).toBeVisible();
  expect(placements).toBe(0);
  await loginAsCustomer(page);
  await openCheckout(page);
  await expect(theme.orders.getOrderSummary()).toContainText('E2E Espresso');
});
