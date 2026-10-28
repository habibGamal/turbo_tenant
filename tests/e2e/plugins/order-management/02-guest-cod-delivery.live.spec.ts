import { test, expect } from '../../helpers/live-fixture';

test.describe('OrderManagement Suite 02: Guest Cash On Delivery (COD) Order Placement', () => {
  test('places guest delivery order with COD and redirects to order tracking', async ({ page, theme }) => {
    // 1. Add product to cart
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200),
      page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
    ]);

    const addBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      addBtn.click(),
    ]);

    // 2. Go to checkout
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/checkout-data') && r.status() === 200),
      page.goto('/checkout', { waitUntil: 'domcontentloaded' }),
    ]);

    // 3. Fill Guest form details
    await page.locator('[data-testid="guest-name-input"], input#guest_name').first().fill('Habib Test Customer');
    await page.locator('[data-testid="guest-phone-input"], input#guest_phone').first().fill('01012345678');

    // Select Area dropdown
    const areaTrigger = page.locator('[data-testid="guest-area-trigger"], #guest_area').first();
    await expect(areaTrigger).toBeVisible({ timeout: 5000 });
    await areaTrigger.click();
    const firstOption = page.locator('[role="option"]').first();
    await expect(firstOption).toBeVisible({ timeout: 5000 });
    await firstOption.click();

    // Fill Street and Building
    const streetInput = page.locator('#guest_street, input[id="guest_street"]').first();
    if (await streetInput.isVisible()) {
      await streetInput.fill('El-Tahrir Street 10');
    }

    const buildingInput = page.locator('#guest_building, input[id="guest_building"]').first();
    if (await buildingInput.isVisible()) {
      await buildingInput.fill('Building 4B');
    }

    // 4. Submit Order
    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const result = await placeOrderResponse.json();
    expect(result.success).toBe(true);
    expect(result.order_id).toBeDefined();
    expect(result.order_number).toBeDefined();
    expect(result.redirect_type).toBe('internal');
    expect(result.redirect_url).toContain('/orders/track');

    // 5. Verify redirection to tracking page
    await expect(page).toHaveURL(/orders\/track/);

    // 6. Verify cart is cleared
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });
  });
});
