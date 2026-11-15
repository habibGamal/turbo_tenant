import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

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
    const { locations } = await openCheckout(page);

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
    expect(result.data.order_number).toBeDefined();
    expect(result.data.tracking_token).toBeTruthy();
    expect(result.data.requires_payment).toBe(false);

    // 5. Verify redirection to tracking page
    await expect(page).toHaveURL(/orders\/track/);
    expect(new URL(page.url()).searchParams.get('order_number')).toBe(result.data.order_number);
    expect(new URL(page.url()).searchParams.get('token')).toBe(result.data.tracking_token);
    expect(new URL(page.url()).searchParams.has('phone')).toBe(false);
    const trackingResponse = await trackingResponsePromise;
    expect(trackingResponse.status()).toBe(200);
    const tracked = await trackingResponse.json();
    const selectedArea = locations.governorates.flatMap((governorate: { areas?: { id: number; shipping_cost: number }[] }) => governorate.areas ?? [])[0];
    expect(tracked.status).toBe('success');
    expect(tracked.data.type).toBe('web_delivery');
    expect(tracked.data.payment_method).toBe('cod');
    expect(tracked.data.delivery_address).toEqual(expect.objectContaining({
      area_id: selectedArea.id,
      street: 'El-Tahrir Street 10',
      building: 'Building 4B',
    }));
    expect(Number(tracked.data.delivery_fee)).toBe(Number(selectedArea.shipping_cost));

    // 6. Verify cart is cleared
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });
  });
});
