import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 06: Line Item Removal', () => {
  test('removes item from multi-item cart and verifies subtotal and badge update', async ({ page, theme }) => {
    // 1. Add Product 15 (166 EGP)
    await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200);
    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 2. Add Product 16 (135 EGP)
    await page.goto('/products/16', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/16') && r.status() === 200);
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 3. Open cart -> verify 2 items and initial combined subtotal (301.00 EGP)
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);
    const items = theme.cart.getItems();
    await expect(items).toHaveCount(2);

    const subtotal = theme.cart.getSubtotal();
    await expect(subtotal).toContainText('301.00');

    // 4. Remove first item
    const firstRemoveBtn = theme.cart.getItemRemoveButton(items.nth(0));
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.request().method() === 'DELETE' && r.status() === 200),
      firstRemoveBtn.click(),
    ]);

    // Exactly 1 item should remain
    await expect(items).toHaveCount(1);
    await expect(subtotal).toContainText('135.00');

    // Header badge updates to 1
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toHaveText('1');

    // 5. Remove remaining last item -> transitions to empty cart state
    const secondRemoveBtn = theme.cart.getItemRemoveButton(items.nth(0));
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.request().method() === 'DELETE' && r.status() === 200),
      secondRemoveBtn.click(),
    ]);

    // Empty cart state appears
    await expect(theme.cart.getEmptyCartMessage()).toBeVisible({ timeout: 5000 });
  });
});
