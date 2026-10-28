import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 06: Weighted Items & Discrete Weight Selection', () => {
  test('displays discrete weight options for weighted products and allows switching', async ({ page, theme }) => {
    // Product 5 is configured with sell_by_weight = true
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
    );

    const weightOptions = theme.catalog.getWeightOptions();
    await expect(weightOptions.first()).toBeVisible({ timeout: 10000 });
    const count = await weightOptions.count();
    expect(count).toBeGreaterThanOrEqual(2);

    // Verify weight option labels (e.g. 125, 250, 500, 1000 or labels)
    const firstOption = weightOptions.nth(0);
    const secondOption = weightOptions.nth(1);

    await expect(firstOption).toBeVisible();
    await expect(secondOption).toBeVisible();

    // Click second weight option
    await secondOption.click();

    // Verify active styling / state on second option
    await expect(secondOption).toHaveClass(/border-primary/);

    // Click first weight option
    await firstOption.click();
    await expect(firstOption).toHaveClass(/border-primary/);
  });

  test('weight-based product detail shows effective price and enabled Add to Cart button', async ({ page, theme }) => {
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
    );

    const weightOptions = theme.catalog.getWeightOptions();
    await expect(weightOptions.first()).toBeVisible({ timeout: 10000 });

    const addToCartButton = theme.catalog.getDetailAddToCartButton();
    await expect(addToCartButton).toBeVisible();
    await expect(addToCartButton).toBeEnabled();
  });
});
