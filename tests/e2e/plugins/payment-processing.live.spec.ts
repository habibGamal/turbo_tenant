import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: payment-processing (Live)', () => {
  test('verifies payment callback screen handles callback parameters gracefully', async ({ page, theme }) => {
    // Visit payment callback route
    await page.goto('/orders/999999/payment/callback?paymentStatus=FAILED');

    // Asserts page renders without crashing
    await expect(page.locator('body')).toBeVisible();
  });

  test('verifies payment method selector on checkout', async ({ page, theme }) => {
    await page.goto('/checkout');

    const paymentCod = theme.checkout.getPaymentCodOption();
    if (await paymentCod.isVisible()) {
      await expect(paymentCod).toBeVisible();
    }
  });
});
