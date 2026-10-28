import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 08: Empty Cart Experience', () => {
  test('displays empty state illustration and navigates back to store on Start Shopping CTA', async ({ page, theme }) => {
    // 1. Navigate directly to /cart on fresh isolated browser context
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });

    // 2. Verify Empty Cart card is visible
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 10000 });

    // 3. Verify Start Shopping button is present
    const startShoppingBtn = theme.cart.getStartShoppingButton();
    await expect(startShoppingBtn).toBeVisible();

    // 4. Click Start Shopping button
    await startShoppingBtn.click();

    // 5. Verify redirection to catalog / home
    await expect(page).toHaveURL(/\/(menu)?$/);
    const productCards = theme.catalog.getProductCards();
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
  });
});
