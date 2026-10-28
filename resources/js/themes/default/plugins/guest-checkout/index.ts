import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedGuestOrderTracking from './ThemedGuestOrderTracking';
import ThemedGuestCheckout from './ThemedGuestCheckout';

/**
 * Register theme overrides for GuestCheckout plugin in the 'default' theme.
 */
export function registerGuestCheckoutThemeOverride() {
  registerThemeOverride('default', 'order.tracking', ThemedGuestOrderTracking);
  registerThemeOverride('default', 'checkout.guest.form', ThemedGuestCheckout);
}

export { ThemedGuestOrderTracking, ThemedGuestCheckout };
