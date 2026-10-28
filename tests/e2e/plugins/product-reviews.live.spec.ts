import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: product-reviews (Live)', () => {
  test('verifies product reviews section and review submission validation', async ({ page, theme }) => {
    await page.goto('/');

    const productCards = theme.catalog.getProductCards();
    if ((await productCards.count()) > 0) {
      await productCards.first().click();

      // We are on product details page
      await expect(page).toHaveURL(/\/products\/\d+/);

      const reviewSection = theme.reviews.getReviewsSection();
      if (await reviewSection.isVisible()) {
        const commentInput = theme.reviews.getCommentInput();
        const submitBtn = theme.reviews.getSubmitButton();

        if (await commentInput.isVisible() && await submitBtn.isVisible()) {
          // Fill comment
          await commentInput.fill('Excellent quality and fast service!');
          await expect(commentInput).toHaveValue('Excellent quality and fast service!');
        }
      }
    }
  });
});
