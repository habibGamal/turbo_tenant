import { test, expect } from '../../helpers/live-fixture';
import { loginAsCustomer } from '../../helpers/auth';

test.describe('OrderManagement Suite 09: Order Details Inspection (/orders/:id)', () => {
  test('navigates from order list to order details and inspects timeline, items, and total', async ({ page, theme }) => {
    // 1. Authenticate as customer
    await loginAsCustomer(page, 'admin@example.com', 'password');

    // 2. Go to /orders
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders') && r.status() === 200),
      page.goto('/orders', { waitUntil: 'domcontentloaded' }),
    ]);

    // 3. Click View Details on first order
    const firstCard = theme.orders.getOrderCards().first();
    const viewDetailsBtn = theme.orders.getViewOrderDetailsButton(firstCard);
    await expect(viewDetailsBtn).toBeVisible();

    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/orders/') && r.status() === 200),
      viewDetailsBtn.click(),
    ]);

    // 4. Verify Order Details page loaded
    await expect(page).toHaveURL(/\/orders\/\d+/);

    const detailsContainer = theme.orders.getOrderDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });

    // 5. Verify Timeline, Items, and Total are present
    const timeline = theme.orders.getOrderStatusTimeline();
    await expect(timeline).toBeVisible();

    const itemsList = theme.orders.getOrderItemsList();
    await expect(itemsList).toBeVisible();

    const totalDisplay = theme.orders.getDetailOrderTotal();
    await expect(totalDisplay).toBeVisible();
  });

  test('displays order not found notice when navigating to invalid order id', async ({ page }) => {
    // 1. Authenticate as customer
    await loginAsCustomer(page, 'admin@example.com', 'password');

    // 2. Direct visit to non-existent order
    await page.goto('/orders/99999999', { waitUntil: 'domcontentloaded' });

    // 3. Verify 404 state rendered
    const heading = page.getByRole('heading', { level: 2 });
    await expect(heading).toBeVisible({ timeout: 10000 });
    await expect(heading.locator('..').locator('a[href="/orders"]')).toBeVisible();
  });
});
