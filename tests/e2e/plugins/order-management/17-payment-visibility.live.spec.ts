import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

test('checkout shows online payment when the tenant enables it', async ({ page, theme }) => {
  await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
  const payload = await page.evaluate(async () => (await fetch('/api/tenant/bootstrap')).json());
  expect(payload.active_plugins).toContain('payment-processing');
  expect(payload.settings.online_payments_enabled).toBe(true);

  await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);

  const card = page.getByRole('radio', { name: /credit card|بطاقة ائتمان/i });
  await expect(card).toBeVisible();
  await card.click();
  await expect(card).toBeChecked();
});

test('checkout hides online payment when the tenant disables it', async ({ page, theme }) => {
  // Change the bootstrap settings only in this browser; keep tenant settings untouched.
  await page.addInitScript(() => {
    let bootstrap: any;
    Object.defineProperty(window, '__TENANT_BOOTSTRAP__', {
      configurable: true,
      get: () => bootstrap,
      set: value => {
        bootstrap = value;
        if (bootstrap?.settings) {
          bootstrap.settings.online_payments_enabled = false;
          bootstrap.settings.cod_fee = 25;
        }
      },
    });
  });
  await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
  await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
    theme.catalog.getDetailAddToCartButton().click(),
  ]);
  await openCheckout(page);
  await expect(page.getByRole('radio', { name: /credit card|بطاقة ائتمان/i })).toHaveCount(0);
  await expect(page.getByRole('radio', { name: /cash on delivery|الدفع عند الاستلام/i })).toBeVisible();
  await expect(page.locator('label[for="cod"]')).toContainText('25');
  await expect(theme.orders.getOrderSummary()).toContainText('191');
});
