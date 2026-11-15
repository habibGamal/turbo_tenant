import { lazy } from 'react';
import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
const ThemedGuestOrderTracking = lazy(() => import('./ThemedGuestOrderTracking'));
const ThemedGuestCheckout = lazy(() => import('./ThemedGuestCheckout'));

/**
 * Register theme overrides for GuestCheckout plugin in the 'default' theme.
 */
export function registerGuestCheckoutThemeOverride() {
  registerThemeOverride('default', 'order.tracking', ThemedGuestOrderTracking);
  registerThemeOverride('default', 'checkout.guest.form', ThemedGuestCheckout);
}

export { ThemedGuestOrderTracking, ThemedGuestCheckout };
