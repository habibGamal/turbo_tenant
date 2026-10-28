import { registerThemeOverride, SlotName } from '@/core/slots/slot-registry';
import ThemedCustomerFavorites from './ThemedCustomerFavorites';
import ThemedFavoriteButton from './ThemedFavoriteButton';

/**
 * Register default theme overrides for CustomerFavorites plugin.
 */
export function registerCustomerFavoritesThemeOverride(): void {
  registerThemeOverride('default', 'favorites.page', ThemedCustomerFavorites);
  registerThemeOverride('default', 'product.card.favorite', ThemedFavoriteButton);
}

export { default as ThemedCustomerFavorites } from './ThemedCustomerFavorites';
export { default as ThemedFavoriteButton } from './ThemedFavoriteButton';
