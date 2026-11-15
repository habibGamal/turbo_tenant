import { test, expect } from '../../helpers/live-fixture';

test('first delivery address is entered inline, handles save errors, and is passed to the order', async ({ page, theme }) => {
  test.setTimeout(90_000);
  await page.addInitScript(() => {
    let bootstrap: any;
    Object.defineProperty(window, '__TENANT_BOOTSTRAP__', { configurable: true, get: () => bootstrap, set: value => { bootstrap = { ...value, user: { id: 101, name: 'Test Customer', email: 'test@example.com' } }; } });
  });
  await page.route('**/api/tenant/auth/user', route => route.fulfill({ json: { user: { id: 101, name: 'Test Customer', email: 'test@example.com' } } }));
  await page.route('**/api/cart', route => route.fulfill({ json: { status: 'success', data: { total: 50, subtotal: 50, items: [{ id: 1, product_id: 1, quantity: 1, price: 50, subtotal: 50, product: { id: 1, name: 'Test item' }, extras: [] }] } } }));
  await page.route('**/api/locations-delivery', route => route.fulfill({ json: { status: 'success', data: { addresses: [], branches: [{ id: 1, name: 'Test branch', is_active: true }], governorates: [{ id: 1, name: 'Cairo', is_active: true, areas: [{ id: 2, name: 'Test area', shipping_cost: 10, is_active: true }] }] } } }));

  let saveAttempts = 0;
  let addressPayload: any;
  await page.route('**/api/locations-delivery/addresses', async route => {
    if (route.request().method() !== 'POST') return route.continue();
    saveAttempts++;
    addressPayload = route.request().postDataJSON();
    if (saveAttempts === 1) {
      return route.fulfill({ status: 422, json: { message: 'Invalid address', errors: { street: ['Test address error'] } } });
    }
    return route.fulfill({ status: 201, json: { status: 'success', data: { id: 987654, ...addressPayload } } });
  });
  let orderPayload: any;
  await page.route('**/api/orders/place', async route => {
    orderPayload = route.request().postDataJSON();
    await route.fulfill({ status: 200, json: { status: 'success', data: { order_id: 987654, order_number: 'TEST-987654', requires_payment: false } } });
  });

  await page.goto('/checkout', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#checkout-address-area')).toBeVisible();
  await page.locator('#checkout-address-area').click();
  await page.getByRole('option').first().click();
  await page.locator('#checkout-address-phone').fill('1012345678');
  await page.locator('#checkout-address-street').fill('Test Street');
  await page.locator('#checkout-address-building').fill('12');
  await page.locator('#checkout-address-floor').fill('2');
  await page.locator('#checkout-address-apartment').fill('3');
  await page.getByTestId('place-order-button').click();
  await expect(page.getByText('Test address error')).toBeVisible();
  expect(orderPayload).toBeUndefined();

  await page.getByTestId('place-order-button').click();
  await expect.poll(() => orderPayload?.address_id).toBe(987654);
  expect(saveAttempts).toBe(2);
  expect(addressPayload.street).toBe('Test Street');
  expect(addressPayload.area_id).toBeGreaterThan(0);
  await expect(page).toHaveURL(/\/orders\/987654/);
});
