import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: coupons-promotions (Live)', () => {
  test('validates invalid coupon code and displays error alert', async ({ page, theme }) => {
    // Navigate to cart
    await page.goto('/cart');

    const couponForm = theme.coupons.getCouponForm();
    if (await couponForm.isVisible()) {
      // Enter invalid coupon
      await theme.coupons.applyCoupon('INVALID_EXPIRED_CODE_123');

      // Assert error message appears
      const errorMsg = theme.coupons.getErrorMessage();
      await expect(errorMsg).toBeVisible({ timeout: 5000 });
    } else {
      // If coupon form is displayed on checkout instead
      await page.goto('/checkout');
      const checkoutCouponForm = theme.coupons.getCouponForm();
      if (await checkoutCouponForm.isVisible()) {
        await theme.coupons.applyCoupon('INVALID_EXPIRED_CODE_123');
        const errorMsg = theme.coupons.getErrorMessage();
        await expect(errorMsg).toBeVisible({ timeout: 5000 });
      }
    }
  });
});
