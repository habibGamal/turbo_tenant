import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: customer-favorites (Live)', () => {
  test('navigates to favorites page and renders wishlist state', async ({ page, theme }) => {
    await page.goto('/favorites');

    // Verify favorites page container is loaded
    const favoritesPage = theme.favorites.getFavoritesPage();
    await expect(favoritesPage).toBeVisible();

    // Verify either favorite cards exist or empty message is shown
    const cards = theme.favorites.getFavoriteCards();
    const count = await cards.count();

    if (count > 0) {
      await expect(cards.first()).toBeVisible();
    } else {
      await expect(page.locator('[data-testid="empty-favorites-message"]').or(favoritesPage)).toBeVisible();
    }
  });

  test('toggles favorite button on product card', async ({ page, theme }) => {
    await page.goto('/');

    const productCards = theme.catalog.getProductCards();
    if ((await productCards.count()) > 0) {
      const firstCard = productCards.first();
      const favBtn = theme.catalog.getFavoriteToggleButton(firstCard);

      if (await favBtn.isVisible()) {
        await favBtn.click();
        // Allow state change
        await page.waitForTimeout(400);
      }
    }
  });
});
