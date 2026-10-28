import { useState, useEffect, useCallback } from 'react';

const STORAGE_KEY = 'favorites';

function getStoredFavorites(): number[] {
  if (typeof window === 'undefined') return [];
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored ? JSON.parse(stored) : [];
  } catch (error) {
    console.error('Failed to read favorites from localStorage:', error);
    return [];
  }
}

export function useFavoritesStorage() {
  const [favoriteIds, setFavoriteIds] = useState<number[]>(getStoredFavorites);

  useEffect(() => {
    const handleStorageChange = (event: StorageEvent) => {
      if (event.key === STORAGE_KEY) {
        try {
          setFavoriteIds(JSON.parse(event.newValue || '[]'));
        } catch {
          setFavoriteIds([]);
        }
      }
    };

    const handleLocalUpdate = () => {
      setFavoriteIds(getStoredFavorites());
    };

    window.addEventListener('storage', handleStorageChange);
    window.addEventListener('favorites-updated', handleLocalUpdate);

    return () => {
      window.removeEventListener('storage', handleStorageChange);
      window.removeEventListener('favorites-updated', handleLocalUpdate);
    };
  }, []);

  const saveFavorites = useCallback((newFavorites: number[]) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(newFavorites));
      setFavoriteIds(newFavorites);
      window.dispatchEvent(new Event('favorites-updated'));
    } catch (error) {
      console.error('Failed to persist favorites to localStorage:', error);
    }
  }, []);

  const addToFavorites = useCallback((productId: number) => {
    const current = getStoredFavorites();
    if (!current.includes(productId)) {
      saveFavorites([...current, productId]);
    }
  }, [saveFavorites]);

  const removeFromFavorites = useCallback((productId: number) => {
    const current = getStoredFavorites();
    saveFavorites(current.filter((id) => id !== productId));
  }, [saveFavorites]);

  const toggleFavorite = useCallback((productId: number) => {
    const current = getStoredFavorites();
    if (current.includes(productId)) {
      saveFavorites(current.filter((id) => id !== productId));
    } else {
      saveFavorites([...current, productId]);
    }
  }, [saveFavorites]);

  const isFavorite = useCallback((productId: number) => {
    return favoriteIds.includes(productId);
  }, [favoriteIds]);

  const clearFavorites = useCallback(() => {
    saveFavorites([]);
  }, [saveFavorites]);

  return {
    favoriteIds,
    addToFavorites,
    removeFromFavorites,
    toggleFavorite,
    isFavorite,
    clearFavorites,
  };
}
