import { lazy } from 'react';
import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
const DefaultGuestOrderTracking = lazy(() => import('./components/DefaultGuestOrderTracking'));
const DefaultGuestCheckout = lazy(() => import('./components/DefaultGuestCheckout'));

export function registerGuestCheckoutPlugin(slot: SlotName = 'order.tracking') {
  registerPluginDefault(slot, DefaultGuestOrderTracking);
  registerPluginDefault('checkout.guest.form', DefaultGuestCheckout);
}

export { DefaultGuestOrderTracking, DefaultGuestCheckout };
export * from './api/useGuestOrderTracking';
export * from './types';
