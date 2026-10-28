import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 02: Product Variants', () => {
  test('selects variant on product details page and renders variant badge in cart', async ({ page, theme }) => {
    // Product 5 (Simple Coffee) has variants: "Mid" and "سادة"
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    const variantOptions = theme.catalog.getVariantOptions();
    await expect(variantOptions.first()).toBeVisible({ timeout: 10000 });
    const variantCount = await variantOptions.count();
    expect(variantCount).toBeGreaterThanOrEqual(2);

    // Select second variant (سادة)
    await variantOptions.nth(1).click();

    // Add to cart via details button
    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // Header badge updates
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
    await expect(badge).toHaveText('1');

    // Navigate to /cart and verify variant indicator
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const firstItem = theme.cart.getItem(0);
    await expect(firstItem).toBeVisible();
    const variantLabel = theme.cart.getItemVariant(firstItem);
    await expect(variantLabel).toBeVisible();
    await expect(variantLabel).toContainText('سادة');
  });

  test('creates separate line items for distinct variants and aggregates same variant', async ({ page, theme }) => {
    // 1. Add Variant A (first variant: Mid) with default weight (125g)
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    const variantOptions = theme.catalog.getVariantOptions();
    await expect(variantOptions.first()).toBeVisible({ timeout: 10000 });
    const weightOptions = theme.catalog.getWeightOptions();
    await expect(weightOptions.first()).toBeVisible({ timeout: 10000 });

    // Select Mid + 125g
    await variantOptions.nth(0).click();
    await weightOptions.nth(0).click();

    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 2. Add Variant B (second variant: سادة) with same weight (125g)
    await variantOptions.nth(1).click();
    await weightOptions.nth(0).click();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // Verify 2 distinct items in badge
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toHaveText('2', { timeout: 5000 });

    // Open cart -> verify 2 distinct line items
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);
    const items = theme.cart.getItems();
    await expect(items).toHaveCount(2);

    // 3. Add Variant B (سادة) with same weight (125g) again -> verify quantity aggregates
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);
    await variantOptions.nth(1).click();
    await weightOptions.nth(0).click();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // Open cart -> item count is still 2 line items, but total quantity increased
    await theme.cart.open();
    await expect(items).toHaveCount(2);
  });
});
