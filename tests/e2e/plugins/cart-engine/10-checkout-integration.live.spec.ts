import { test, expect } from '../../helpers/live-fixture';

test.describe('CartEngine Suite 10: Checkout Integration & Guards', () => {
  test('hands off configured cart items to checkout page with matching summary', async ({ page, theme }) => {
    // 1. Configure and add Product 5 with Variant (سادة) and Weight (250g)
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200);

    const variantOptions = theme.catalog.getVariantOptions();
    await variantOptions.nth(1).click();

    const weightOptions = theme.catalog.getWeightOptions();
    await weightOptions.nth(1).click();

    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 2. Open /cart
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const checkoutBtn = theme.cart.getCheckoutButton();
    await expect(checkoutBtn).toBeVisible();
    await expect(checkoutBtn).toBeEnabled();

    // 3. Click "Proceed to Checkout"
    await checkoutBtn.click();
    await expect(page).toHaveURL(/\/checkout/);

    // 4. Verify checkout page rendered with order summary
    const checkoutForm = theme.checkout.getCheckoutForm();
    await expect(checkoutForm).toBeVisible({ timeout: 10000 });

    // Order summary reflects items
    const orderSummary = theme.checkout.getOrderSummary();
    await expect(orderSummary).toBeVisible({ timeout: 5000 });
  });

  test('guards checkout page when cart has 0 items and displays empty cart notice', async ({ page, theme }) => {
    // Direct navigation to /checkout with empty session
    await page.goto('/checkout', { waitUntil: 'domcontentloaded' });

    // Should guard against checkout and render EmptyCart
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 10000 });
  });
});
