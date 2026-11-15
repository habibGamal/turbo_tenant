import { lazy } from 'react';
import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
const ThemedLocationsDelivery = lazy(() => import('./ThemedLocationsDelivery'));

export function registerLocationsDeliveryThemeOverride(slot: SlotName = 'profile.addresses') {
  registerThemeOverride('default', slot, ThemedLocationsDelivery);
}

export { ThemedLocationsDelivery };
