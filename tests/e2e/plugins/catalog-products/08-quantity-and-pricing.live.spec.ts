import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 08: Quantity Steppers & Price Calculation', () => {
  test('increments and decrements quantity on standard products with lower boundary check', async ({ page, theme }) => {
    // Product 15 is a standard product with sell_by_weight = false
    await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/15') && r.status() === 200
    );

    const qtyValue = theme.catalog.getQuantityValue();
    await expect(qtyValue).toBeVisible({ timeout: 10000 });
    await expect(qtyValue).toHaveText('1');

    const decBtn = theme.catalog.getQuantityDecrementButton();
    const incBtn = theme.catalog.getQuantityIncrementButton();

    // Boundary: Decrement button is disabled when quantity is 1
    await expect(decBtn).toBeDisabled();

    // Increment quantity to 2
    await incBtn.click();
    await expect(qtyValue).toHaveText('2');
    await expect(decBtn).toBeEnabled();

    // Increment quantity to 3
    await incBtn.click();
    await expect(qtyValue).toHaveText('3');

    // Decrement quantity back to 2
    await decBtn.click();
    await expect(qtyValue).toHaveText('2');

    // Decrement quantity back to 1
    await decBtn.click();
    await expect(qtyValue).toHaveText('1');

    // Lower boundary disabled again
    await expect(decBtn).toBeDisabled();
  });
});
