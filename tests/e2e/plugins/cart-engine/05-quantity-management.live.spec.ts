import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 05: Quantity Management & Boundaries', () => {
  test('increments and decrements standard item quantity and enforces lower boundary', async ({ page, theme }) => {
    // Navigate to Product 15 (Special Simple 200g - standard unit product, base price 166 EGP)
    await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/15') && r.status() === 200);

    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // Navigate to /cart
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const firstItem = theme.cart.getItem(0);
    await expect(firstItem).toBeVisible();

    // Verify initial subtotal
    const subtotal = theme.cart.getSubtotal();
    await expect(subtotal).toContainText('166.00');

    // 1. Increment quantity (1 -> 2)
    const incBtn = theme.cart.getQuantityIncrementButton(firstItem);
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.status() === 200),
      incBtn.click(),
    ]);

    // Subtotal should now reflect 2 * 166 = 332.00 EGP
    await expect(subtotal).toContainText('332.00', { timeout: 5000 });

    // 2. Decrement quantity (2 -> 1)
    const decBtn = theme.cart.getQuantityDecrementButton(firstItem);
    await expect(decBtn).toBeEnabled();

    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.status() === 200),
      decBtn.click(),
    ]);

    // Subtotal should return to 166.00 EGP
    await expect(subtotal).toContainText('166.00', { timeout: 5000 });

    // 3. Lower boundary rule: at quantity 1, decrement is disabled
    await expect(decBtn).toBeDisabled();
  });
});
