import { registerThemeOverride } from '@/core/slots/slot-registry';
import ThemedCouponForm from './ThemedCouponForm';

export function registerCouponsPromotionsThemeOverride(): void {
  registerThemeOverride('default', 'checkout.coupon.form', ThemedCouponForm);
}

export { ThemedCouponForm };
