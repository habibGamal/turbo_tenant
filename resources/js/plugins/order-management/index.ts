import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultOrdersList from './components/DefaultOrdersList';

/**
 * Register default fallback component for OrderManagement
 */
export function registerOrderManagementPlugin(slot: SlotName = 'customer.orders') {
    registerPluginDefault(slot, DefaultOrdersList);
}

export { default as DefaultOrdersList } from './components/DefaultOrdersList';
export * from './api/useOrders';
export * from './api/useOrderDetails';
export * from './api/useCheckoutData';
export * from './api/usePlaceOrder';
export * from './types';
