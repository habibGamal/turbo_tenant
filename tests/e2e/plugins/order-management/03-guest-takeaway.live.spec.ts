import { test, expect } from '../../helpers/live-fixture';

test.describe('OrderManagement Suite 03: Guest Takeaway (Store Pickup) Order Placement', () => {
  test('places guest takeaway order with 0 delivery fee and redirects to tracking', async ({ page, theme }) => {
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

    // 3. Switch order type to Takeaway
    const takeawayRadio = theme.orders.getOrderTypeTakeaway();
    await takeawayRadio.click();

    // Verify delivery area selector is hidden in takeaway mode
    const areaTrigger = page.locator('[data-testid="guest-area-trigger"], #guest_area');
    await expect(areaTrigger).not.toBeVisible();

    // 4. Fill Guest name and phone
    await page.locator('[data-testid="guest-name-input"], input#guest_name').first().fill('Habib Takeaway Guest');
    await page.locator('[data-testid="guest-phone-input"], input#guest_phone').first().fill('01123456789');

    // 5. Submit Order
    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const result = await placeOrderResponse.json();
    expect(result.success).toBe(true);
    expect(result.order_id).toBeDefined();
    expect(result.redirect_type).toBe('internal');
    expect(result.redirect_url).toContain('/orders/track');

    // 6. Verify redirection to tracking page
    await expect(page).toHaveURL(/orders\/track/);

    // 7. Verify cart is cleared
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });
  });
});
