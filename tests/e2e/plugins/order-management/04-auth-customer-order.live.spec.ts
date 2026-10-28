import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';

test.describe('OrderManagement Suite 04: Authenticated Customer Order Placement', () => {
  test('places delivery order as logged-in customer and navigates to order details', async ({ page, theme }) => {
    // 1. Authenticate as seeded customer
    await loginAsCustomer(page, 'admin@example.com', 'password');

    // 2. Add product to cart
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200),
      page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
    ]);

    const addBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      addBtn.click(),
    ]);

    // 3. Go to checkout
    const [bootstrapResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/checkout-data') && r.status() === 200),
      page.goto('/checkout', { waitUntil: 'domcontentloaded' }),
    ]);

    const bootstrapJson = await bootstrapResponse.json();
    expect(bootstrapJson.data.is_guest).toBe(false);
    expect(bootstrapJson.data.addresses.length).toBeGreaterThan(0);

    // Verify guest form is not rendered for authenticated customer
    await expect(page.locator('[data-testid="guest-name-input"]')).not.toBeVisible();

    // 4. Submit order
    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const result = await placeOrderResponse.json();
    expect(result.success).toBe(true);
    expect(result.order_id).toBeDefined();
    expect(result.redirect_type).toBe('internal');
    expect(result.redirect_url).toBe(`/orders/${result.order_id}`);

    // 5. Verify navigation to Order Details view
    await expect(page).toHaveURL(new RegExp(`/orders/${result.order_id}`));
    const detailsContainer = theme.orders.getOrderDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });

    // 6. Verify order number displayed matches returned order
    const orderNumberEl = theme.orders.getOrderNumber(detailsContainer);
    await expect(orderNumberEl).toBeVisible();
    await expect(orderNumberEl).toContainText(String(result.order_number || result.order_id));
  });
});
