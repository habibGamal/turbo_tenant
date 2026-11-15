import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';
import { pricePattern } from '../../helpers/price';

for (const scenario of [
  { language: 'ar', direction: 'rtl', viewport: { width: 1280, height: 800 }, submit: 'place-order-button' },
  { language: 'en', direction: 'ltr', viewport: { width: 390, height: 844 }, submit: 'mobile-place-order-button' },
]) {
  test(`guest checkout survives refresh and double submission in ${scenario.language}`, async ({ page, theme }) => {
    await page.setViewportSize(scenario.viewport);
    await page.addInitScript(language => localStorage.setItem('i18nextLng', language), scenario.language);
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200),
      page.goto('/products/15', { waitUntil: 'domcontentloaded' }),
    ]);
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      theme.catalog.getDetailAddToCartButton().click(),
    ]);
    await openCheckout(page);
    await expect(page.locator('html')).toHaveAttribute('dir', scenario.direction);
    await expect(theme.orders.getOrderSummary()).toBeVisible();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(theme.orders.getOrderSummary()).toContainText('E2E Espresso');
    await expect(theme.orders.getOrderSummary()).toContainText(pricePattern(166));
    await expect(theme.orders.getOrderSummary()).toContainText(scenario.language === 'ar' ? /ج\.م/ : /EGP/i);
    // Missing delivery details should show an inline error and focus the first field.
    await page.getByTestId(scenario.submit).click();
    await expect(page.locator('p.text-destructive').first()).toBeVisible();
    await expect(page.getByTestId('guest-name-input')).toBeFocused();
    await theme.orders.getOrderTypeTakeaway().click();
    await page.getByTestId('guest-name-input').fill(`E2E ${scenario.language} Guest`);
    await page.getByTestId('guest-phone-input').fill('01066665555');

    let placements = 0;
    page.on('request', request => {
      if (new URL(request.url()).pathname === '/api/orders/place' && request.method() === 'POST') placements++;
    });
    const [response] = await Promise.all([
      page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'),
      page.getByTestId(scenario.submit).dblclick(),
    ]);
    expect(response.status()).toBe(200);
    const placed = await response.json();
    expect(placed.status).toBe('success');
    await expect(page).toHaveURL(/\/orders\/track/);
    await page.waitForLoadState('networkidle');
    expect(placements).toBe(1);
    expect(new URL(page.url()).searchParams.get('token')).toBe(placed.data.tracking_token);
    await page.goBack({ waitUntil: 'domcontentloaded' });
    await expect(theme.cart.getEmptyCartMessage()).toBeVisible();
    expect(placements).toBe(1);
  });
}
