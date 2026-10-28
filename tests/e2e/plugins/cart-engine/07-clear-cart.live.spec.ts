import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 07: Clear Cart', () => {
  test('purges entire cart via Clear Cart button and syncs empty state', async ({ page, theme }) => {
    // 1. Add Product 15 to cart
    await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200);

    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 2. Open /cart
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const items = theme.cart.getItems();
    await expect(items).toHaveCount(1);

    // 3. Click "Clear Cart" button
    const clearBtn = theme.cart.getClearCartButton();
    await expect(clearBtn).toBeVisible();

    // Clearing asks for confirmation first
    await clearBtn.click();
    const confirmBtn = theme.cart.getClearCartConfirmButton();
    await expect(confirmBtn).toBeVisible();

    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart') && r.request().method() === 'DELETE' && r.status() === 200),
      confirmBtn.click(),
    ]);

    // 4. Verify Empty Cart message is displayed
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });

    // 5. Verify badge count is removed/hidden
    const badge = theme.cart.getBadgeCount();
    await expect(badge).not.toBeVisible();
  });
});
