import { registerPluginDefault, SlotName } from '@/core/slots/slot-registry';
import DefaultCustomerFavorites from './components/DefaultCustomerFavorites';
import DefaultFavoriteButton from './components/DefaultFavoriteButton';

/**
 * Register default fallback components for the CustomerFavorites plugin.
 */
export function registerCustomerFavoritesPlugin(): void {
  registerPluginDefault('favorites.page', DefaultCustomerFavorites);
  registerPluginDefault('product.card.favorite', DefaultFavoriteButton);
}

export { default as DefaultCustomerFavorites } from './components/DefaultCustomerFavorites';
export { default as DefaultFavoriteButton } from './components/DefaultFavoriteButton';
export * from './api/useCustomerFavorites';
export * from './api/useFavoritesStorage';
export * from './api/useToggleFavoriteMutation';
export * from './types';
