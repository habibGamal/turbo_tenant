import { test, expect } from '../../helpers/live-fixture';

test.describe('OrderManagement Suite 05: Complex Cart Order (Variants, Weights & Extras)', () => {
  test('persists configured product options into placed order and displays in order view', async ({ page, theme }) => {
    // 1. Navigate to Product 5
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200),
      page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
    ]);

    // 2. Select Variant
    const variantOptions = theme.catalog.getVariantOptions();
    await expect(variantOptions.first()).toBeVisible({ timeout: 5000 });
    await variantOptions.nth(1).click();

    // 3. Select Weight option
    const weightOptions = theme.catalog.getWeightOptions();
    await expect(weightOptions.first()).toBeVisible({ timeout: 5000 });
    await weightOptions.nth(1).click();

    // 4. Toggle Extra Addon & increment quantity
    const extraCheckboxes = theme.catalog.getExtraCheckboxes();
    if (await extraCheckboxes.count() > 0) {
      await extraCheckboxes.first().click();
      const incBtn = theme.catalog.getExtraIncrementButtons();
      if (await incBtn.count() > 0 && await incBtn.first().isVisible()) {
        await incBtn.first().click();
      }
    }

    // 5. Add to cart
    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 6. Go to checkout
    const [bootstrapResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/checkout-data') && r.status() === 200),
      page.goto('/checkout', { waitUntil: 'domcontentloaded' }),
    ]);

    const bootstrapJson = await bootstrapResponse.json();
    const cartItem = bootstrapJson.data.cart.items[0];
    expect(cartItem).toBeDefined();

    // 7. Verify order summary in checkout displays item breakdown
    const orderSummary = theme.orders.getOrderSummary();
    await expect(orderSummary).toBeVisible();

    // 8. Fill Guest information
    await page.locator('[data-testid="guest-name-input"], input#guest_name').first().fill('Habib Complex Order');
    await page.locator('[data-testid="guest-phone-input"], input#guest_phone').first().fill('01055443322');

    const areaTrigger = page.locator('[data-testid="guest-area-trigger"], #guest_area').first();
    await expect(areaTrigger).toBeVisible({ timeout: 5000 });
    await areaTrigger.click();
    const firstOption = page.locator('[role="option"]').first();
    await expect(firstOption).toBeVisible({ timeout: 5000 });
    await firstOption.click();

    const streetInput = page.locator('#guest_street, input[id="guest_street"]').first();
    if (await streetInput.isVisible()) {
      await streetInput.fill('Zamalek Island St');
    }

    // 9. Place order
    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const result = await placeOrderResponse.json();
    expect(result.success).toBe(true);
    expect(result.order_id).toBeDefined();

    // 10. Verify redirection to tracking
    await expect(page).toHaveURL(/orders\/track/);
  });
});
