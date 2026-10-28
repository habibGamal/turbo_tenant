import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 04: Addons & Extras', () => {
  test('toggles extra option, increments addon quantity, and verifies subtotal math in cart', async ({ page, theme }) => {
    // Navigate to Product 5 (Simple Coffee - has extra option "Gensing 1 g")
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    const extraCheckboxes = theme.catalog.getExtraCheckboxes();
    await expect(extraCheckboxes.first()).toBeVisible({ timeout: 10000 });

    // Toggle Gensing addon
    await extraCheckboxes.first().check();
    await expect(extraCheckboxes.first()).toBeChecked();

    // Increment extra quantity to 2x
    const extraIncButtons = theme.catalog.getExtraIncrementButtons();
    await expect(extraIncButtons.first()).toBeVisible({ timeout: 5000 });
    await extraIncButtons.first().click();

    // Select weight 250g (0.250 kg)
    const weightOptions = theme.catalog.getWeightOptions();
    await weightOptions.nth(1).click();

    // Add to cart
    // Math: (600 * 0.250) + (10 EGP * 2) = 150 + 20 = 170.00 EGP
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

    // Verify extras container displays 2x quantity
    const extrasDisplay = theme.cart.getItemExtras(firstItem);
    await expect(extrasDisplay).toBeVisible();
    await expect(extrasDisplay).toContainText('2x');

    // Verify subtotal matches mathematical calculation (170.00 EGP)
    const subtotal = theme.cart.getSubtotal();
    await expect(subtotal).toContainText('170.00');
  });

  test('verifies ultra combo (variant + weight + addon) price accuracy in cart', async ({ page, theme }) => {
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    // 1. Variant: Select سادة (index 1)
    const variantOptions = theme.catalog.getVariantOptions();
    await variantOptions.nth(1).click();

    // 2. Weight: Select 500g (index 2: 0.500 kg)
    const weightOptions = theme.catalog.getWeightOptions();
    await weightOptions.nth(2).click();

    // 3. Addon: Toggle Gensing and keep at 1x (+10 EGP)
    const extraCheckboxes = theme.catalog.getExtraCheckboxes();
    await extraCheckboxes.first().check();

    // Math: (600 * 0.500) + (10 * 1) = 300 + 10 = 310.00 EGP
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

    // Check all 3 dimensions rendered on line item
    await expect(theme.cart.getItemVariant(firstItem)).toContainText('سادة');
    await expect(theme.cart.getItemExtras(firstItem)).toBeVisible();

    // Verify exact subtotal
    const subtotal = theme.cart.getSubtotal();
    await expect(subtotal).toContainText('310.00');
  });
});
