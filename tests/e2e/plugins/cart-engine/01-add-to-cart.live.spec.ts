import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 01: Add to Cart & Badge Sync', () => {
  test('adds standard product to cart and increments header badge', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/sections') && r.status() === 200);

    const activeAddButtons = page.locator('[data-testid="product-add-to-cart"]:not([disabled])');
    await expect(activeAddButtons.first()).toBeVisible({ timeout: 10000 });

    // Click Add to Cart on catalog card
    const [response] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      activeAddButtons.first().click(),
    ]);
    expect(response.ok()).toBeTruthy();

    // Verify header cart badge updates to 1
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
    await expect(badge).toHaveText('1');
  });

  test('adds multiple distinct products and synchronizes badge count', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/sections') && r.status() === 200);

    const activeAddButtons = page.locator('[data-testid="product-add-to-cart"]:not([disabled])');
    await expect(activeAddButtons.first()).toBeVisible({ timeout: 10000 });
    const count = await activeAddButtons.count();
    test.skip(count < 2, 'Need at least 2 active products to test distinct additions');

    // Add First Product
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      activeAddButtons.nth(0).click(),
    ]);

    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
    await expect(badge).toHaveText('1');

    // Add Second Product
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      activeAddButtons.nth(1).click(),
    ]);

    // Verify header cart badge updates to 2
    await expect(badge).toHaveText('2', { timeout: 5000 });

    // Navigate to /cart and verify both line items exist
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);
    const items = theme.cart.getItems();
    await expect(items).toHaveCount(2);
  });
});
