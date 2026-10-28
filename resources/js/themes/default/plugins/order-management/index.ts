import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedOrdersList from './ThemedOrdersList';

export function registerOrderManagementThemeOverride(slot: SlotName = 'customer.orders') {
    registerThemeOverride('default', slot, ThemedOrdersList);
}

export { default as ThemedOrdersList } from './ThemedOrdersList';
