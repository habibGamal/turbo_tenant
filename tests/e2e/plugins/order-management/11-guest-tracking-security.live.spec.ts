import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

test('guest tracking rejects tampered tokens and wrong phones but accepts the correct phone', async ({ page, theme }) => {
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200),
    page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
  ]);
  await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);
  await theme.orders.getOrderTypeTakeaway().click();
  await page.locator('[data-testid="guest-name-input"]').fill('E2E Tracking Guest');
  await page.locator('[data-testid="guest-phone-input"]').fill('01088887777');

  const tracking = page.waitForResponse(r =>
    new URL(r.url()).pathname === '/api/guest-checkout/track' && r.request().method() === 'POST'
  );
  const [placement] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
    theme.orders.getPlaceOrderButton().click(),
  ]);
  const placed = await placement.json();
  expect(placed.status).toBe('success');
  expect(placed.data.tracking_token).toBeTruthy();
  const validTracking = await tracking;
  expect(validTracking.status()).toBe(200);
  await expect(page.getByText('E2E Simple Coffee', { exact: true })).toBeVisible();

  const forgedURL = new URL(page.url());
  forgedURL.searchParams.set('token', `${placed.data.tracking_token}x`);
  const [forgedResponse] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/guest-checkout/track' && r.status() === 404),
    page.goto(forgedURL.toString(), { waitUntil: 'domcontentloaded' }),
  ]);
  const rejected = await forgedResponse.json();
  expect(rejected.status).toBe('error');
  await expect(page.getByText('Order not found', { exact: true })).toBeVisible();
  await expect(page.getByText('E2E Simple Coffee', { exact: true })).toHaveCount(0);

  // A real order number must not make a different phone number sufficient.
  const phone = page.getByTestId('track-phone-input');
  await phone.fill('01000000000');
  await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/guest-checkout/track' && r.status() === 404),
    page.getByTestId('track-submit-button').click(),
  ]);
  await expect(page.getByText('Order not found', { exact: true })).toBeVisible();
  await expect(page.getByText('E2E Simple Coffee', { exact: true })).toHaveCount(0);

  // The same UI permits recovery using the phone used for the order.
  await phone.fill('01088887777');
  const [recovered] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/guest-checkout/track' && r.status() === 200),
    page.getByTestId('track-submit-button').click(),
  ]);
  expect((await recovered.json()).data.order_number).toBe(placed.data.order_number);
  await expect(page.getByText('E2E Simple Coffee', { exact: true })).toBeVisible();
});
