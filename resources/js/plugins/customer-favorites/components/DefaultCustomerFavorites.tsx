import React from 'react';
import { useCustomerFavorites } from '../api/useCustomerFavorites';
import { useFavoritesStorage } from '../api/useFavoritesStorage';

export interface DefaultCustomerFavoritesProps {
  onProductClick?: (productId: number) => void;
  className?: string;
}

export default function DefaultCustomerFavorites({
  onProductClick,
  className = '',
}: DefaultCustomerFavoritesProps) {
  const { products, isLoading, isError, totalCount } = useCustomerFavorites();
  const { removeFromFavorites } = useFavoritesStorage();

  if (isLoading) {
    return (
      <div className={`p-8 text-center text-muted-foreground animate-pulse ${className}`}>
        Loading favorites...
      </div>
    );
  }

  if (isError) {
    return (
      <div className={`p-6 text-center text-destructive border border-destructive/20 rounded-lg ${className}`}>
        Failed to load favorites. Please try again.
      </div>
    );
  }

  if (totalCount === 0 || products.length === 0) {
    return (
      <div className={`p-8 text-center text-muted-foreground border rounded-lg bg-card ${className}`}>
        <h3 className="text-lg font-semibold mb-2">No favorites yet</h3>
        <p className="text-sm">Items you save as favorites will appear here.</p>
      </div>
    );
  }

  return (
    <div className={`space-y-4 ${className}`}>
      <div className="flex items-center justify-between pb-2 border-b">
        <h2 className="text-xl font-bold">My Favorites</h2>
        <span className="text-sm text-muted-foreground">{products.length} items</span>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {products.map((product) => (
          <div
            key={product.id}
            className="border rounded-lg p-4 bg-card text-card-foreground flex flex-col justify-between"
          >
            <div
              className="cursor-pointer"
              onClick={() => onProductClick?.(product.id)}
            >
              {product.image && (
                <img
                  src={product.image}
                  alt={product.name}
                  className="w-full h-40 object-cover rounded mb-3"
                />
              )}
              <h4 className="font-semibold text-base">{product.name}</h4>
              <p className="text-sm font-medium text-primary mt-1">
                {product.price} EGP
              </p>
            </div>

            <button
              type="button"
              onClick={() => removeFromFavorites(product.id)}
              className="mt-3 text-xs text-destructive hover:underline self-start"
            >
              Remove from favorites
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
