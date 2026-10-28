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

        const addBtn = theme.combos.getComboAddToCartButton(firstCombo);
        await expect(addBtn).toBeVisible();
      }
    }
  });
});
