import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 03: Weighted Items', () => {
  test('calculates weighted price, handles dynamic weight changes, and enforces multiplier boundaries', async ({ page, theme }) => {
    // 1. Navigate to Product 5 (Simple Coffee - sell_by_weight: true)
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    const weightOptions = theme.catalog.getWeightOptions();
    await expect(weightOptions.first()).toBeVisible({ timeout: 10000 });

    // Select 250g (index 1: 0.250 kg)
    await weightOptions.nth(1).click();

    // Add to cart
    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // Open /cart
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const firstItem = theme.cart.getItem(0);
    await expect(firstItem).toBeVisible();

    // Verify weight dropdown is present
    const weightSelect = theme.cart.getItemWeightSelect(firstItem);
    await expect(weightSelect).toBeVisible();

    // Verify initial subtotal for 250g (0.250 * 600 = 150.00 EGP)
    const subtotal = theme.cart.getSubtotal();
    await expect(subtotal).toContainText('150.00');

    // 2. Dynamic in-cart weight change: Switch from 250g to 500g (0.500 kg)
    await weightSelect.click();
    const option500g = page.locator('[role="option"]:has-text("500")').first();
    await expect(option500g).toBeVisible();

    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.status() === 200),
      option500g.click(),
    ]);

    // Subtotal should now reflect 500g (0.500 * 600 = 300.00 EGP)
    await expect(subtotal).toContainText('300.00', { timeout: 5000 });

    // 3. Multiplier Stepper: Increment weight multiplier (1x -> 2x)
    const incBtn = theme.cart.getQuantityIncrementButton(firstItem);
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.status() === 200),
      incBtn.click(),
    ]);

    // Multiplier 2x of 500g = 1.0kg -> Subtotal should now reflect 600.00 EGP
    await expect(subtotal).toContainText('600.00', { timeout: 5000 });

    // 4. Boundary enforcement: Decrement back to 1x
    const decBtn = theme.cart.getQuantityDecrementButton(firstItem);
    await expect(decBtn).toBeEnabled();

    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items/') && r.status() === 200),
      decBtn.click(),
    ]);

    await expect(subtotal).toContainText('300.00', { timeout: 5000 });

    // Decrement button should now be disabled (multiplier is 1)
    await expect(decBtn).toBeDisabled();
  });
});
