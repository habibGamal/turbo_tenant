import { test, expect } from '../../helpers/live-fixture';

test.describe('OrderManagement Suite 01: Checkout Data Bootstrap & Form Initialization', () => {
  test('fetches bootstrap payload and renders complete checkout form for guest', async ({ page, theme }) => {
    // 1. Add an item to ensure active cart session
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/catalog-products/5') && r.status() === 200),
      page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
    ]);

    const addBtn = theme.catalog.getDetailAddToCartButton();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/cart/items') && r.status() === 201),
      addBtn.click(),
    ]);

    // 2. Navigate to /checkout and intercept bootstrap data
    const [bootstrapResponse] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/checkout-data') && r.status() === 200),
      page.goto('/checkout', { waitUntil: 'domcontentloaded' }),
    ]);

    const json = await bootstrapResponse.json();
    expect(json.status).toBe('success');
    expect(json.data.cart).toBeDefined();
    expect(json.data.branches.length).toBeGreaterThan(0);
    expect(json.data.governorates.length).toBeGreaterThan(0);
    expect(json.data.is_guest).toBe(true);

    // 3. Verify storefront DOM renders components
    const checkoutForm = theme.orders.getCheckoutForm();
    await expect(checkoutForm).toBeVisible({ timeout: 10000 });

    const orderSummary = theme.orders.getOrderSummary();
    await expect(orderSummary).toBeVisible();

    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    await expect(placeOrderBtn).toBeVisible();
    await expect(placeOrderBtn).toBeEnabled();

    // Verify Guest form fields appear for unauthenticated guest
    await expect(page.locator('input[id="name"], input[name="name"], [data-testid="guest-name-input"]').first()).toBeVisible();
    await expect(page.locator('input[id="phone"], input[name="phone"], [data-testid="guest-phone-input"]').first()).toBeVisible();
  });
});
