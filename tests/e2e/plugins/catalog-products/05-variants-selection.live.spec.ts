import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 05: Variant Selection & Dynamic Pricing', () => {
  test('displays variant options with prices and dynamically recalculates total price on selection', async ({ page, theme }) => {
    // Product 5 has 2 variants: 'Mid' (600.00 EGP) and 'سادة' (700.00 EGP)
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
    );

    const variants = theme.catalog.getVariantOptions();
    await expect(variants.first()).toBeVisible({ timeout: 10000 });
    const count = await variants.count();
    expect(count).toBeGreaterThanOrEqual(2);

    const firstVariant = variants.nth(0);
    const secondVariant = variants.nth(1);

    // 1. Assert prices are displayed on variant option cards
    const firstVariantText = await firstVariant.textContent();
    expect(firstVariantText).toContain('600.00');

    const secondVariantText = await secondVariant.textContent();
    expect(secondVariantText).toContain('700.00');

    // 2. Assert initial total price reflects first variant (600.00 EGP)
    const totalPrice = theme.catalog.getTotalPrice();
    await expect(totalPrice).toBeVisible();
    await expect(totalPrice).toContainText('600.00');

    // 3. Select second variant -> assert dynamic price updates to 700.00 EGP
    await secondVariant.click();
    await expect(totalPrice).toContainText('700.00');

    // Verify checked state
    const secondRadio = secondVariant.locator('button[role="radio"], input[type="radio"]');
    if (await secondRadio.count() > 0) {
      await expect(secondRadio).toHaveAttribute('data-state', 'checked');
    }

    // 4. Switch back to first variant -> assert price returns to 600.00
    await firstVariant.click();
    await expect(totalPrice).toContainText('600.00');
  });

  test('selecting a variant preserves ability to add to cart', async ({ page, theme }) => {
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
    );

    const variants = theme.catalog.getVariantOptions();
    await expect(variants.first()).toBeVisible({ timeout: 10000 });

    // Select second variant
    await variants.nth(1).click();

    // Add to cart button is enabled
    const addToCartButton = theme.catalog.getDetailAddToCartButton();
    await expect(addToCartButton).toBeEnabled();
  });
});
