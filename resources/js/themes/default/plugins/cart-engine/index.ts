import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedCart from './ThemedCart';
import ThemedCartButton from './ThemedCartButton';

/**
 * Register default theme overrides for CartEngine plugin.
 */
export function registerCartEngineThemeOverride(): void {
    registerThemeOverride('default', 'header.actions.cart' as SlotName, ThemedCartButton);
    registerThemeOverride('default', 'cart.page' as SlotName, ThemedCart);
}

export { default as ThemedCart } from './ThemedCart';
export { default as ThemedCartButton } from './ThemedCartButton';
