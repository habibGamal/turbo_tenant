/**
 * Backward compatibility facade for useFavorites hook.
 * Delegates to decoupled CustomerFavorites plugin module.
 */
import { useFavoritesStorage } from '@/plugins/customer-favorites';

export function useFavorites() {
  const {
    favoriteIds,
    addToFavorites,
    removeFromFavorites,
    toggleFavorite,
    isFavorite,
    clearFavorites,
  } = useFavoritesStorage();

  return {
    favorites: favoriteIds,
    addToFavorites,
    removeFromFavorites,
    toggleFavorite,
    isFavorite,
    clearFavorites,
  };
}

export default useFavorites;
