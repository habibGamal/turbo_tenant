import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

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
    await expect(extraCheckboxes.first()).toBeVisible();
    await extraCheckboxes.first().check();
    const incBtn = theme.catalog.getExtraIncrementButtons().first();
    await expect(incBtn).toBeVisible();
    await incBtn.click();

    // 5. Add to cart
    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      detailAddBtn.click(),
    ]);

    // 6. Go to checkout
    const { cart } = await openCheckout(page);

    const cartItem = cart.items[0];
    expect(cartItem.variant.name).toBe('سادة');
    expect(Number(cartItem.weight_option_value.value)).toBe(0.25);
    expect(cartItem.extras).toEqual(expect.arrayContaining([
      expect.objectContaining({ name: 'Gensing 1 g', quantity: 2 }),
    ]));

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

    // 10. Verify redirection to tracking
    await expect(page).toHaveURL(/orders\/track/);
    expect(new URL(page.url()).searchParams.get('token')).toBe(result.data.tracking_token);
    const trackingResponse = await trackingResponsePromise;
    expect(trackingResponse.status()).toBe(200);
    const tracked = await trackingResponse.json();
    expect(tracked.status).toBe('success');
    expect(tracked.data.order_number).toBe(result.data.order_number);
    expect(tracked.data.items[0]).toEqual(expect.objectContaining({
      product_id: cartItem.product_id,
      variant_id: cartItem.variant.id,
      weight_option_value_id: cartItem.weight_option_value.id,
      extras: expect.arrayContaining([expect.objectContaining({ extra_name: 'Gensing 1 g', quantity: 2 })]),
    }));
    await expect(page.getByText('E2E Simple Coffee', { exact: true })).toBeVisible();
    await expect(page.getByText('سادة', { exact: true })).toBeVisible();
    await expect(page.getByText('+ Gensing 1 g', { exact: true })).toBeVisible();
  });
});
