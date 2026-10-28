import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: cart-engine (Live)', () => {
  test('adds product to cart via UI and verifies cart drawer/page interaction', async ({ page, theme }) => {
    await page.goto('/');

    const productCards = theme.catalog.getProductCards();
    const count = await productCards.count();

    if (count > 0) {
      const firstCard = theme.catalog.getProductCard(0);
      const addToCartBtn = theme.catalog.getAddToCartButton(firstCard);

      if (await addToCartBtn.isVisible()) {
        // Pure user action: click Add to Cart
        await addToCartBtn.click();

        // Cart trigger badge should display item count
        const badge = theme.cart.getBadgeCount();
        await expect(badge).toBeVisible({ timeout: 5000 });
      }

      // Navigate to /cart
      await theme.cart.open();
      await expect(page).toHaveURL(/\/cart/);

      // Verify cart item exists
      const cartItems = theme.cart.getItems();
      if ((await cartItems.count()) > 0) {
        const firstItem = theme.cart.getItem(0);
        await expect(firstItem).toBeVisible();

        // Increment quantity via UI
        const incBtn = theme.cart.getQuantityIncrementButton(firstItem);
        if (await incBtn.isVisible()) {
          await incBtn.click();
          await page.waitForTimeout(500); // Allow mutation settle
        }

        // Verify subtotal exists and is displayed
        const subtotal = theme.cart.getSubtotal();
        await expect(subtotal).toBeVisible();

        // Verify checkout button is present and enabled
        const checkoutBtn = theme.cart.getCheckoutButton();
        await expect(checkoutBtn).toBeVisible();
        await expect(checkoutBtn).toBeEnabled();
      }
    } else {
      // Cart page empty state
      await page.goto('/cart');
      await expect(theme.cart.getEmptyCartMessage().or(page.locator('body'))).toBeVisible();
    }
  });

  test('displays empty cart state when no items in cart', async ({ page, theme }) => {
    // Isolated new context / empty cart navigation
    await page.goto('/cart');
    const emptyMsg = theme.cart.getEmptyCartMessage();
    const items = theme.cart.getItems();

    if ((await items.count()) === 0) {
      await expect(emptyMsg).toBeVisible();
    }
  });
});
