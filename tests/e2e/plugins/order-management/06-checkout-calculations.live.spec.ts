import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';
import { pricePattern } from '../../helpers/price';

test.describe('OrderManagement Suite 06: Checkout Calculations & Dynamic Fees', () => {
  test('dynamically updates order summary totals when switching order types and areas', async ({ page, theme }) => {
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
    const { cart, locations } = await openCheckout(page);

    const cartTotal = Number(cart.total);

    const orderSummary = theme.orders.getOrderSummary();
    await expect(orderSummary).toBeVisible();

    // 3. Switch to Takeaway
    const takeawayRadio = theme.orders.getOrderTypeTakeaway();
    await takeawayRadio.click();

    // In takeaway mode, delivery fee is 0 (or free), so total equals cart subtotal
    await expect(orderSummary).toContainText(pricePattern(cartTotal));

    // 4. Switch back to Delivery
    const deliveryRadio = theme.orders.getOrderTypeDelivery();
    await deliveryRadio.click();

    // 5. Select Area dropdown
    const areaTrigger = page.locator('[data-testid="guest-area-trigger"], #guest_area').first();
    await expect(areaTrigger).toBeVisible({ timeout: 5000 });
    await areaTrigger.click();

    const firstOption = page.locator('[role="option"]').first();
    await expect(firstOption).toBeVisible({ timeout: 5000 });
    await firstOption.click();

    // The selected area's fee contributes to the delivery total.
    const firstArea = locations.governorates.flatMap((governorate: { areas?: { shipping_cost: number }[] }) => governorate.areas ?? [])[0];
    expect(firstArea).toBeDefined();
    expect(Number(firstArea.shipping_cost)).toBeGreaterThan(0);
    await expect(orderSummary).toContainText(pricePattern(cartTotal + Number(firstArea.shipping_cost)));
  });
});
