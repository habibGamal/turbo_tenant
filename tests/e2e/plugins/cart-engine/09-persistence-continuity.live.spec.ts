import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 09: Persistence & Session Continuity', () => {
  test('preserves configured item across hard page reloads', async ({ page, theme }) => {
    // 1. Configure and add Product 5 with Variant (سادة) and Weight (250g)
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    const variantOptions = theme.catalog.getVariantOptions();
    await variantOptions.nth(1).click();

    const weightOptions = theme.catalog.getWeightOptions();
    await weightOptions.nth(1).click();

    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 2. Open /cart and verify initial load
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const firstItem = theme.cart.getItem(0);
    await expect(firstItem).toBeVisible();
    await expect(theme.cart.getItemVariant(firstItem)).toContainText('سادة');
    await expect(theme.cart.getSubtotal()).toContainText('150.00');

    // 3. Hard browser reload
    await page.reload({ waitUntil: 'domcontentloaded' });

    // 4. Verify all options and calculations persist identical after reload
    await expect(firstItem).toBeVisible({ timeout: 10000 });
    await expect(theme.cart.getItemVariant(firstItem)).toContainText('سادة');
    await expect(theme.cart.getSubtotal()).toContainText('150.00');
    await expect(theme.cart.getBadgeCount()).toHaveText('1');
  });

  test('maintains cart state across multi-page client router transitions', async ({ page, theme }) => {
    // 1. Add Product 15
    await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200);

    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 2. Client-side navigate to /favorites via theme or page
    await page.goto('/favorites', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/favorites/);
    await expect(theme.cart.getBadgeCount()).toHaveText('1');

    // 3. Client-side navigate to /menu
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/menu/);
    await expect(theme.cart.getBadgeCount()).toHaveText('1');

    // 4. Return to /cart
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);
    const items = theme.cart.getItems();
    await expect(items).toHaveCount(1);
    await expect(theme.cart.getSubtotal()).toContainText('166.00');
  });
});
