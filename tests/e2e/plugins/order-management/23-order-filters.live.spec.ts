import { test, expect } from '../../helpers/live-fixture';

test('search and status filters query order history and can be cleared', async ({ page }) => {
  await page.addInitScript(() => {
    let bootstrap: any;
    Object.defineProperty(window, '__TENANT_BOOTSTRAP__', { configurable: true, get: () => bootstrap, set: value => { bootstrap = { ...value, user: { id: 101, name: 'Test Customer', email: 'test@example.com' } }; } });
  });
  await page.route('**/api/tenant/auth/user', route => route.fulfill({ json: { user: { id: 101, name: 'Test Customer', email: 'test@example.com' } } }));
  const requests: string[] = [];
  await page.route(/\/api\/orders(?:\?.*)?$/, route => {
    const url = new URL(route.request().url());
    requests.push(url.search);
    const found = url.searchParams.get('search') !== 'missing';
    return route.fulfill({ json: { status: 'success', data: found ? [{ id: 1, order_number: 'TEST-123', created_at: '2026-11-06T12:00:00Z', status: 'pending', payment_status: 'pending', payment_method: 'cod', branch: { name: 'Test branch' }, items: [], total: 60 }] : [], meta: { last_page: 2, current_page: Number(url.searchParams.get('page')) || 1 } } });
  });
  await page.goto('/orders', { waitUntil: 'domcontentloaded' });
  await expect(page.getByTestId('order-history-item')).toBeVisible();
  await page.getByRole('button', { name: '2', exact: true }).click();
  await expect.poll(() => requests.some(q => new URLSearchParams(q).get('page') === '2')).toBe(true);
  await page.locator('#orders-search').fill('missing');
  await expect(page.getByTestId('no-matching-orders')).toBeVisible();
  expect(new URLSearchParams(requests.at(-1)).get('search')).toBe('missing');
  expect(new URLSearchParams(requests.at(-1)).get('page')).toBe('1');
  await page.locator('#orders-status').click();
  await page.getByRole('option').filter({ hasText: /مكتمل|completed/i }).click();
  await expect.poll(() => new URLSearchParams(requests.at(-1)).get('status')).toBe('completed');
  await page.locator('#orders-payment-status').click();
  await page.getByRole('option').filter({ hasText: /فشل|failed/i }).click();
  await expect.poll(() => new URLSearchParams(requests.at(-1)).get('payment_status')).toBe('failed');
  await page.getByTestId('no-matching-orders').getByRole('button').click();
  await expect(page.getByTestId('order-history-item')).toBeVisible();
  await expect(page.locator('#orders-search')).toHaveValue('');
  await expect(page.locator('#orders-status')).toContainText(/كل الحالات|all statuses/i);
  await expect(page.locator('#orders-payment-status')).toContainText(/كل الحالات|all statuses/i);
});
