import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultCart from './components/DefaultCart';
import DefaultCartButton from './components/DefaultCartButton';

/**
 * Register default fallback components for CartEngine plugin
 */
export function registerCartEnginePlugin(): void {
    registerPluginDefault('header.actions.cart' as SlotName, DefaultCartButton);
    registerPluginDefault('cart.page' as SlotName, DefaultCart);
}

export * from './api';
export * from './types';
export { DefaultCart, DefaultCartButton };
