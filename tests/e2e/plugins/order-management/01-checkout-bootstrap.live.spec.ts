import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

test.describe('OrderManagement Suite 01: Checkout Data & Form Initialization', () => {
  test('loads cart and delivery locations and renders the guest checkout form', async ({ page, theme }) => {
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

    // 2. Navigate to /checkout and read the cart and locations responses
    const { cart, locations } = await openCheckout(page);

    expect(cart.items.length).toBeGreaterThan(0);
    expect(locations.branches.length).toBeGreaterThan(0);
    expect(locations.governorates.length).toBeGreaterThan(0);
    expect(locations.addresses).toEqual([]);

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
