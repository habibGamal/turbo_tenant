import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';
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
    const { cart, locations } = await openCheckout(page);

    expect(cart.items.length).toBeGreaterThan(0);
    expect(locations.addresses.length).toBeGreaterThan(0);

    // Verify guest form is not rendered for authenticated customer
    await expect(page.locator('[data-testid="guest-name-input"]')).not.toBeVisible();

    // 4. Submit order
    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const result = await placeOrderResponse.json();
    expect(result.status).toBe('success');
    expect(result.data.order_id).toBeDefined();
    expect(result.data.requires_payment).toBe(false);
    expect(result.data.tracking_token).toBeUndefined();

    // 5. Verify navigation to Order Details view
    await expect(page).toHaveURL(new RegExp(`/orders/${result.data.order_id}`));
    const detailsContainer = theme.orders.getOrderDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });

    // 6. Verify order number displayed matches returned order
    const orderNumberEl = theme.orders.getOrderNumber(detailsContainer);
    await expect(orderNumberEl).toBeVisible();
    await expect(orderNumberEl).toContainText(String(result.data.order_number || result.data.order_id));
  });
});
