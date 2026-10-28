import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultLocationsDelivery from './components/DefaultLocationsDelivery';

/**
 * Register default fallback component for LocationsDelivery.
 * Plugs by default into 'profile.addresses'.
 */
export function registerLocationsDeliveryPlugin(slot: SlotName = 'profile.addresses') {
  registerPluginDefault(slot, DefaultLocationsDelivery);
}

export { DefaultLocationsDelivery };
export * from './api/useLocationsDelivery';
export * from './types';
