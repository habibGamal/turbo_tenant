import { test, expect } from '../../helpers/live-fixture';
import { openCheckout } from '../../helpers/checkout';

test.describe('OrderManagement Suite 07: Validation Safeguards & Boundary Conditions', () => {
  test('guards /checkout when cart is empty and renders empty cart notice', async ({ page, theme }) => {
    // 1. Ensure cart is empty by clearing cart session
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const clearBtn = theme.cart.getClearCartButton();
    if (await clearBtn.isVisible()) {
      await clearBtn.click();
      await theme.cart.getClearCartConfirmButton().click();
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
    await openCheckout(page);

    // 3. Validation errors render inline (no browser alert dialogs)
    let dialogCount = 0;
    page.on('dialog', async (dialog) => {
      dialogCount++;
      await dialog.dismiss();
    });
    const inlineErrors = page.locator('p.text-destructive');

    const placeOrderBtn = theme.orders.getPlaceOrderButton();

    // 4. Click place order with empty name/phone -> inline errors appear
    await placeOrderBtn.click();
    await expect(inlineErrors.first()).toBeVisible({ timeout: 5000 });

    // 5. Fill name and phone, but do NOT select area -> an error is still shown
    await page.locator('[data-testid="guest-name-input"], input#guest_name').first().fill('Habib Validation Test');
    await page.locator('[data-testid="guest-phone-input"], input#guest_phone').first().fill('01011223344');

    await placeOrderBtn.click();
    await expect(inlineErrors.first()).toBeVisible({ timeout: 5000 });
    expect(dialogCount).toBe(0);
  });
});
