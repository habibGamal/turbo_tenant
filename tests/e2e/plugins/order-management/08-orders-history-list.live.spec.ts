import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';

test.describe('OrderManagement Suite 08: Customer Order History List (/orders)', () => {
  test('renders customer past orders with status badges and item summaries', async ({ page, theme }) => {
    // 1. Authenticate as customer with orders
    await loginAsCustomer(page, 'admin@example.com', 'password');

    // 2. Navigate to /orders
    const [ordersResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders') && r.status() === 200),
      page.goto('/orders', { waitUntil: 'domcontentloaded' }),
    ]);

    const json = await ordersResponse.json();
    expect(json.status).toBe('success');
    expect(json.data.data.length).toBeGreaterThan(0);

    // 3. Verify Orders List Container
    const ordersList = theme.orders.getOrdersList();
    await expect(ordersList).toBeVisible({ timeout: 10000 });

    // 4. Verify Individual Order Card elements
    const cards = theme.orders.getOrderCards();
    const count = await cards.count();
    expect(count).toBeGreaterThan(0);

    const firstCard = cards.first();
    const orderNumber = theme.orders.getOrderNumber(firstCard);
    await expect(orderNumber).toBeVisible();

    const statusBadge = theme.orders.getOrderStatusBadge(firstCard);
    await expect(statusBadge).toBeVisible();

    const orderTotal = theme.orders.getOrderTotal(firstCard);
    await expect(orderTotal).toBeVisible();

    const viewDetailsBtn = theme.orders.getViewOrderDetailsButton(firstCard);
    await expect(viewDetailsBtn).toBeVisible();
    await expect(viewDetailsBtn).toBeEnabled();
  });
});
