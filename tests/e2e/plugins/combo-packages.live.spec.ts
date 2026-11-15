import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: combo-packages (Live)', () => {
  test('renders combo packages section and cards if available', async ({ page, theme }) => {
    await page.goto('/');

    const combosSection = theme.combos.getCombosSection();
    if (await combosSection.isVisible()) {
      const cards = theme.combos.getComboCards();
      const count = await cards.count();

      if (count > 0) {
        const firstCombo = cards.first();
        await expect(firstCombo).toBeVisible();

        const title = theme.combos.getComboTitle(firstCombo);
        await expect(title).toBeVisible();

        const price = firstCombo.locator('[data-testid="combo-package-price"]');
        await expect(price).toBeVisible();

        const contents = firstCombo.locator('[data-testid="combo-package-contents"]');
        if (await contents.count() > 0) {
          await expect(contents).toBeVisible();
        }

        const originalPrice = firstCombo.locator('[data-testid="combo-package-original-price"]');
        if (await originalPrice.count() > 0) {
          await expect(originalPrice).toBeVisible();
        }

        const savings = firstCombo.locator('[data-testid="combo-package-savings"]');
        if (await savings.count() > 0) {
          await expect(savings).toBeVisible();
        }

        const availability = firstCombo.locator('[data-testid="combo-package-availability"]');
        if (await availability.count() > 0) {
          await expect(availability).toBeVisible();
        }

        const orderType = firstCombo.locator('[data-testid="combo-package-order-type"]');
        if (await orderType.count() > 0) {
          await expect(orderType).toBeVisible();
        }

        const stock = firstCombo.locator('[data-testid="combo-package-stock"]');
        if (await stock.count() > 0) {
          await expect(stock).toBeVisible();
        }

        const addBtn = theme.combos.getComboAddToCartButton(firstCombo);
        await expect(addBtn).toBeVisible();
      }
    }
  });

  test('renders product combo upsell section if product has combo packages', async ({ page }) => {
    const upsellSection = page.locator('[data-testid="product-combo-upsell"]');
    if (await upsellSection.count() > 0 && await upsellSection.isVisible()) {
      await expect(upsellSection).toBeVisible();
      const upsellItems = page.locator('[data-testid="product-combo-upsell-item"]');
      if (await upsellItems.count() > 0) {
        await expect(upsellItems.first()).toBeVisible();
      }
    }
  });
});
