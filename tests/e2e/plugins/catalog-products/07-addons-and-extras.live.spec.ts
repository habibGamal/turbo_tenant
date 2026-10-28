import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 07: Extras & Addons Customization', () => {
  test('toggles extra option and controls addon quantity steppers', async ({ page, theme }) => {
    // Product 5 has extra option group with 'Gensing 1 g' (allow_quantity: true)
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
    );

    const extraCheckboxes = theme.catalog.getExtraCheckboxes();
    await expect(extraCheckboxes.first()).toBeVisible({ timeout: 10000 });

    const firstCheckbox = extraCheckboxes.first();
    await expect(firstCheckbox).not.toBeChecked();

    // Check extra option
    await firstCheckbox.check();
    await expect(firstCheckbox).toBeChecked();

    // Quantity steppers should appear for allow_quantity items
    const incrementButton = theme.catalog.getExtraIncrementButtons().first();
    const decrementButton = theme.catalog.getExtraDecrementButtons().first();

    if (await incrementButton.isVisible()) {
      // Initially, decrement button is disabled because extra qty is 1
      await expect(decrementButton).toBeDisabled();

      // Click increment to make extra qty = 2
      await incrementButton.click();
      await expect(decrementButton).toBeEnabled();

      // Click decrement to return to 1
      await decrementButton.click();
      await expect(decrementButton).toBeDisabled();
    }

    // Uncheck extra option
    await firstCheckbox.uncheck();
    await expect(firstCheckbox).not.toBeChecked();
  });
});
