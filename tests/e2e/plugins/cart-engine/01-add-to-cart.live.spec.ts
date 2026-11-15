import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 01: Add to Cart & Badge Sync', () => {
  test('adds standard product to cart and increments header badge', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const activeAddButtons = page.locator('[data-testid="product-add-to-cart"]:not([disabled])');
    await expect(activeAddButtons.first()).toBeVisible({ timeout: 10000 });

    // Click Add to Cart on catalog card
    const [response] = await Promise.all([
      page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
      activeAddButtons.first().click(),
    ]);
    expect(response.status()).toBe(201);

    // Verify header cart badge updates to 1
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
    await expect(badge).toHaveText('1');
  });

  test('adds multiple distinct products and synchronizes badge count', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const activeAddButtons = page.locator('[data-testid="product-add-to-cart"]:not([disabled])');
    await expect(activeAddButtons.first()).toBeVisible({ timeout: 10000 });
    const count = await activeAddButtons.count();
    test.skip(count < 2, 'Need at least 2 active products to test distinct additions');

    // Add First Product
    const [firstResponse] = await Promise.all([
      page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
      activeAddButtons.nth(0).click(),
    ]);
    expect(firstResponse.status()).toBe(201);

    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
    await expect(badge).toHaveText('1');

    // Add Second Product
    const [secondResponse] = await Promise.all([
      page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
      activeAddButtons.nth(1).click(),
    ]);
    expect(secondResponse.status()).toBe(201);

    // Verify header cart badge updates to 2
    await expect(badge).toHaveText('2', { timeout: 5000 });

    // Navigate to /cart and verify both line items exist
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);
    const items = theme.cart.getItems();
    await expect(items).toHaveCount(2);
  });
});
