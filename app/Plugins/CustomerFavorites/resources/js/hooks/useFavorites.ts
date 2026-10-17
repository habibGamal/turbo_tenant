import { useState, useEffect } from 'react';

const STORAGE_KEY = 'favorites';

export function useFavorites() {
    const [favorites, setFavorites] = useState<number[]>([]);

    useEffect(() => {
        const storedFavorites = localStorage.getItem(STORAGE_KEY);
        if (storedFavorites) {
            try {
                setFavorites(JSON.parse(storedFavorites));
            } catch (error) {
                console.error('Failed to parse favorites from localStorage', error);
                setFavorites([]);
            }
        }

        const handleStorageChange = (event: StorageEvent) => {
            if (event.key === STORAGE_KEY) {
                try {
                    setFavorites(JSON.parse(event.newValue || '[]'));
                } catch (error) {
                    console.error('Failed to parse favorites from storage event', error);
                }
            }
        };

        window.addEventListener('storage', handleStorageChange);

        // Custom event for same-tab updates
        const handleLocalChange = () => {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                try {
                    setFavorites(JSON.parse(stored));
                } catch (e) {
                    console.error('Failed to parse favorites from local event', e);
                }
            }
        };
        window.addEventListener('favorites-updated', handleLocalChange);

        return () => {
            window.removeEventListener('storage', handleStorageChange);
            window.removeEventListener('favorites-updated', handleLocalChange);
        };
    }, []);

    const saveFavorites = (newFavorites: number[]) => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(newFavorites));
        setFavorites(newFavorites);
        window.dispatchEvent(new Event('favorites-updated'));
    };

    const addToFavorites = (productId: number) => {
        if (!favorites.includes(productId)) {
            const newFavorites = [...favorites, productId];
            saveFavorites(newFavorites);
        }
    };

    const removeFromFavorites = (productId: number) => {
        const newFavorites = favorites.filter((id) => id !== productId);
        saveFavorites(newFavorites);
    };

    const toggleFavorite = (productId: number) => {
        if (favorites.includes(productId)) {
            removeFromFavorites(productId);
        } else {
            addToFavorites(productId);
        }
    };

    const isFavorite = (productId: number) => favorites.includes(productId);

    return {
        favorites,
        addToFavorites,
        removeFromFavorites,
        toggleFavorite,
        isFavorite,
    };
}
