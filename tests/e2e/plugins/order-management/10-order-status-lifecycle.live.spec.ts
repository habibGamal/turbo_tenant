import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';
import { loginAsCustomer } from '../../helpers/auth';
import { integrationTokenFor } from '../../helpers/integration-token';

test.describe('OrderManagement Suite 10: Order Status Lifecycle & POS Sync', () => {
  test('transitions order status via status webhook and reflects in live UI', async ({ page, theme, baseURL }) => {
    const integrationToken = integrationTokenFor(baseURL);
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
    await openCheckout(page);

    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);

    const orderResult = await placeOrderResponse.json();
    expect(orderResult.status).toBe('success');
    const orderNumber = orderResult.data.order_number;
    const orderId = orderResult.data.order_id;

    // 4. Verify landing on Order Details
    await expect(page).toHaveURL(new RegExp(`/orders/${orderId}`));
    const detailsContainer = theme.orders.getOrderDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });

    const statusBadge = theme.orders.getOrderStatusBadge(detailsContainer);
    await expect(statusBadge).toBeVisible();

    // The integration endpoint must reject a request without its bearer token.
    const unauthorized = await page.evaluate(async (num) => {
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
    expect(unauthorized.status).toBe(401);

    const updateStatus = async (status: string, token = integrationToken) => page.evaluate(async ({ num, status, token }) => {
      const res = await fetch('/api/order-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ orderNumber: num, status }),
      });
      return { ok: res.ok, status: res.status, data: await res.json() };
    }, { num: orderNumber, status, token });

    // A wrong integration token must not authorize a status change.
    expect((await updateStatus('processing', `${integrationToken}invalid`)).status).toBe(401);

    // The correct token must not bypass the status transition rules.
    const invalidTransition = await updateStatus('completed');
    expect(invalidTransition.status).toBe(409);
    expect(invalidTransition.data.from_status).toBe('pending');
    expect(invalidTransition.data.to_status).toBe('completed');

    expect((await updateStatus('processing')).ok).toBe(true);

    // 6. Reload order details and verify updated badge
    await Promise.all([
      page.waitForResponse(r => r.url().includes(`/api/orders/${orderId}`) && r.status() === 200),
      page.reload({ waitUntil: 'domcontentloaded' }),
    ]);

    await expect(statusBadge).toContainText(/processing|قيد التحضير|قيد المعالجة/i);

    // Delivery must pass through out_for_delivery before completion.
    expect((await updateStatus('out_for_delivery')).ok).toBe(true);
    expect((await updateStatus('completed')).ok).toBe(true);

    // 8. Reload order details and verify delivered status
    await Promise.all([
      page.waitForResponse(r => r.url().includes(`/api/orders/${orderId}`) && r.status() === 200),
      page.reload({ waitUntil: 'domcontentloaded' }),
    ]);

    await expect(statusBadge).toContainText(/delivered|تم التوصيل|مكتمل/i);
  });
});
