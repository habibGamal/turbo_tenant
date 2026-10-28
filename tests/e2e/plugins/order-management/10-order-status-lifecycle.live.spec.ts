import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';

test.describe('OrderManagement Suite 10: Order Status Lifecycle & POS Sync', () => {
  test('transitions order status via status webhook and reflects in live UI', async ({ page, theme }) => {
    // 1. Authenticate as customer
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

    // 3. Checkout and place order
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/checkout-data') && r.status() === 200),
      page.goto('/checkout', { waitUntil: 'domcontentloaded' }),
    ]);

    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const orderResult = await placeOrderResponse.json();
    expect(orderResult.success).toBe(true);
    const orderNumber = orderResult.order_number;
    const orderId = orderResult.order_id;

    // 4. Verify landing on Order Details
    await expect(page).toHaveURL(new RegExp(`/orders/${orderId}`));
    const detailsContainer = theme.orders.getOrderDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });

    const statusBadge = theme.orders.getOrderStatusBadge(detailsContainer);
    await expect(statusBadge).toBeVisible();

    // 5. Update Status to "processing" via POS/Webhook API
    const updateRes1 = await page.evaluate(async (num) => {
      const res = await fetch('/api/order-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ orderNumber: num, status: 'processing' }),
      });
      return { ok: res.ok, status: res.status, data: await res.json() };
    }, orderNumber);

    expect(updateRes1.ok).toBe(true);

    // 6. Reload order details and verify updated badge
    await Promise.all([
      page.waitForResponse(r => r.url().includes(`/api/orders/${orderId}`) && r.status() === 200),
      page.reload({ waitUntil: 'domcontentloaded' }),
    ]);

    await expect(statusBadge).toContainText(/processing|قيد المعالجة/i);

    // 7. Update Status to "delivered"
    const updateRes2 = await page.evaluate(async (num) => {
      const res = await fetch('/api/order-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ orderNumber: num, status: 'completed' }),
      });
      return { ok: res.ok, status: res.status, data: await res.json() };
    }, orderNumber);

    expect(updateRes2.ok).toBe(true);

    // 8. Reload order details and verify delivered status
    await Promise.all([
      page.waitForResponse(r => r.url().includes(`/api/orders/${orderId}`) && r.status() === 200),
      page.reload({ waitUntil: 'domcontentloaded' }),
    ]);

    await expect(statusBadge).toContainText(/delivered|تم التوصيل|مكتمل/i);
  });
});
