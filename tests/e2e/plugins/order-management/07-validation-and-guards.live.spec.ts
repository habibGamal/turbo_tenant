import { test, expect } from '../../helpers/live-fixture';

test.describe('OrderManagement Suite 07: Validation Safeguards & Boundary Conditions', () => {
  test('guards /checkout when cart is empty and renders empty cart notice', async ({ page, theme }) => {
    // 1. Ensure cart is empty by clearing cart session
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const clearBtn = theme.cart.getClearCartButton();
    if (await clearBtn.isVisible()) {
      await clearBtn.click();
    }

    // 2. Direct visit to /checkout with 0 items
    await page.goto('/checkout', { waitUntil: 'domcontentloaded' });

    // 3. Verify EmptyCart notice is displayed
    const emptyMsg = theme.cart.getEmptyCartMessage();
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });
  });

  test('enforces client-side validation for missing guest details and unselected area', async ({ page, theme }) => {
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
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/checkout-data') && r.status() === 200),
      page.goto('/checkout', { waitUntil: 'domcontentloaded' }),
    ]);

    // 3. Listen for validation dialogs
    const dialogMessages: string[] = [];
    page.on('dialog', async (dialog) => {
      dialogMessages.push(dialog.message());
      await dialog.dismiss();
    });

    const placeOrderBtn = theme.orders.getPlaceOrderButton();

    // 4. Click place order with empty name/phone -> triggers validation
    await placeOrderBtn.click();
    expect(dialogMessages.length).toBeGreaterThan(0);

    // 5. Fill name and phone, but do NOT select area
    await page.locator('[data-testid="guest-name-input"], input#guest_name').first().fill('Habib Validation Test');
    await page.locator('[data-testid="guest-phone-input"], input#guest_phone').first().fill('01011223344');

    const previousCount = dialogMessages.length;
    await placeOrderBtn.click();
    expect(dialogMessages.length).toBeGreaterThan(previousCount);
  });
});
