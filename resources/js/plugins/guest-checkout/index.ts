import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultGuestOrderTracking from './components/DefaultGuestOrderTracking';
import DefaultGuestCheckout from './components/DefaultGuestCheckout';

export function registerGuestCheckoutPlugin(slot: SlotName = 'order.tracking') {
  registerPluginDefault(slot, DefaultGuestOrderTracking);
  registerPluginDefault('checkout.guest.form', DefaultGuestCheckout);
}

export { DefaultGuestOrderTracking, DefaultGuestCheckout };
export * from './api/useGuestOrderTracking';
export * from './types';
