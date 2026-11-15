import { test, expect } from '../../helpers/live-fixture';

test('orders page keeps its heading and cards inside a responsive container', async ({ page }, testInfo) => {
  await page.addInitScript(() => {
    let bootstrap: any;
    Object.defineProperty(window, '__TENANT_BOOTSTRAP__', { configurable: true, get: () => bootstrap, set: value => { bootstrap = { ...value, user: { id: 101, name: 'Test Customer', email: 'test@example.com' } }; } });
  });
  await page.route('**/api/tenant/auth/user', route => route.fulfill({ json: { user: { id: 101, name: 'Test Customer', email: 'test@example.com' } } }));
  await page.route(/\/api\/orders(?:\?.*)?$/, route => route.fulfill({ json: {
    status: 'success', data: [{ id: 1, order_number: 'TEST-123', created_at: '2026-11-06T12:00:00Z', status: 'pending', payment_status: 'pending', payment_method: 'cod', branch: { name: 'Test branch' }, address: { street: 'Test street', building: '1', area: { name: 'Test area' } }, items: [{ id: 1, product_name: 'Test item', quantity: '1', total: 50 }], total: 60 }], meta: { last_page: 1, current_page: 1 }
  } }));
  await page.goto('/orders', { waitUntil: 'domcontentloaded' });
  const heading = page.getByRole('heading', { level: 1 });
  const card = page.getByTestId('order-history-item');
  await expect(heading).toBeVisible();
  await expect(card).toBeVisible();
  await expect(card.getByText('TEST-123')).toBeVisible();
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    const box = await card.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.x).toBeGreaterThanOrEqual(15);
    expect(box!.x + box!.width).toBeLessThanOrEqual(width - 15);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect(heading).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath(`orders-${width}.png`), fullPage: true });
  }
});
