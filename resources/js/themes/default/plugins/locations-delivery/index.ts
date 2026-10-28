import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedLocationsDelivery from './ThemedLocationsDelivery';

export function registerLocationsDeliveryThemeOverride(slot: SlotName = 'profile.addresses') {
  registerThemeOverride('default', slot, ThemedLocationsDelivery);
}

export { ThemedLocationsDelivery };
