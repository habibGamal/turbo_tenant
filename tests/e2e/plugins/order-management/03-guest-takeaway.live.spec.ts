import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

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
    await openCheckout(page);

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
    const trackingResponsePromise = page.waitForResponse(r =>
      new URL(r.url()).pathname === '/api/guest-checkout/track' && r.request().method() === 'POST'
    );
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const result = await placeOrderResponse.json();
    expect(result.status).toBe('success');
    expect(result.data.order_id).toBeDefined();
    expect(result.data.tracking_token).toBeTruthy();
    expect(result.data.requires_payment).toBe(false);

    // 6. Verify redirection to tracking page
    await expect(page).toHaveURL(/orders\/track/);
    expect(new URL(page.url()).searchParams.get('order_number')).toBe(result.data.order_number);
    expect(new URL(page.url()).searchParams.get('token')).toBe(result.data.tracking_token);
    expect(new URL(page.url()).searchParams.has('phone')).toBe(false);
    const trackingResponse = await trackingResponsePromise;
    expect(trackingResponse.status()).toBe(200);
    const tracked = await trackingResponse.json();
    expect(tracked.status).toBe('success');
    expect(tracked.data.order_number).toBe(result.data.order_number);
    expect(tracked.data.type).toBe('web_takeaway');
    expect(Number(tracked.data.delivery_fee)).toBe(0);

    // 7. Verify cart is cleared
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });
  });
});
