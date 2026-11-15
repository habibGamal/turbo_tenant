import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';
import { openCheckout } from '../../helpers/checkout';

test('submitting the same customer cart from two tabs creates only one order', async ({ page, context, theme }) => {
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
  const orderCount = () => page.evaluate(async () => {
    const response = await fetch('/api/orders?per_page=1', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error(`Could not read order history: ${response.status}`);
    return (await response.json()).meta.total as number;
  });
  const before = await orderCount();
  await openCheckout(page);
  const secondPage = await context.newPage();
  await openCheckout(secondPage);
  const submit = (tab: typeof page) => tab.waitForResponse(r =>
    new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'
  );
  const [firstResponse, secondResponse] = await Promise.all([
    submit(page),
    submit(secondPage),
    page.getByTestId('place-order-button').click(),
    secondPage.getByTestId('place-order-button').click(),
  ]);
  expect([firstResponse.status(), secondResponse.status()].sort()).toEqual([200, 422]);
  await expect.poll(orderCount).toBe(before + 1);
  await secondPage.close();
});
