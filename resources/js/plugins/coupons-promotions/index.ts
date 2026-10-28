import { registerPluginDefault } from '@/core/slots/slot-registry';
import DefaultCouponForm from './components/DefaultCouponForm';

export function registerCouponsPromotionsPlugin(): void {
  registerPluginDefault('checkout.coupon.form', DefaultCouponForm);
}
