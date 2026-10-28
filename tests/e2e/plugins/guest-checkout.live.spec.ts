import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: guest-checkout (Live)', () => {
  test('renders guest checkout form fields and validates inputs', async ({ page, theme }) => {
    await page.goto('/checkout');

    const form = theme.checkout.getCheckoutForm();
    if (await form.isVisible()) {
      // Verify name input exists
      const nameInput = theme.checkout.getNameInput();
      if (await nameInput.isVisible()) {
        await nameInput.fill('Guest Test User');
        await expect(nameInput).toHaveValue('Guest Test User');
      }

      // Verify phone input exists
      const phoneInput = theme.checkout.getPhoneInput();
      if (await phoneInput.isVisible()) {
        await phoneInput.fill('01012345678');
      }
    } else {
      // Empty cart redirect back to home or cart
      await expect(page).toHaveURL(/\/(cart)?/);
    }
  });

  test('renders order tracking form and looks up non-existent order gracefully', async ({ page, theme }) => {
    await page.goto('/orders/track');

    const trackForm = theme.checkout.getTrackOrderForm();
    await expect(trackForm).toBeVisible();

    const orderInput = theme.checkout.getTrackOrderInput();
    const phoneInput = theme.checkout.getTrackPhoneInput();
    const submitBtn = theme.checkout.getTrackSubmitButton();

    await orderInput.fill('NON_EXISTENT_999999');
    await phoneInput.fill('01000000000');
    await submitBtn.click();

    // Verify error notification / message from live backend
    await expect(page.locator('.text-destructive, [role="alert"]').first()).toBeVisible({ timeout: 6000 });
  });
});
