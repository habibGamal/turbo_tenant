import { lazy } from 'react';
import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
const ThemedOrdersList = lazy(() => import('./ThemedOrdersList'));

export function registerOrderManagementThemeOverride(slot: SlotName = 'customer.orders') {
    registerThemeOverride('default', slot, ThemedOrdersList);
}

export { ThemedOrdersList };
