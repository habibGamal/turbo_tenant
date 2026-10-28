import React from 'react';
import ReactPixel from 'react-facebook-pixel';
import { Heart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useFavoritesStorage } from '@/plugins/customer-favorites';

export interface ThemedFavoriteButtonProps {
  productId: number;
  productPrice?: number;
  className?: string;
  variant?: 'secondary' | 'ghost' | 'outline' | 'default';
  size?: 'default' | 'sm' | 'lg' | 'icon';
}

export default function ThemedFavoriteButton({
  productId,
  productPrice,
  className = '',
  variant = 'secondary',
  size = 'icon',
}: ThemedFavoriteButtonProps) {
  const { isFavorite, toggleFavorite } = useFavoritesStorage();
  const active = isFavorite(productId);

  const handleClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();

    const willBeFavorite = !active;
    toggleFavorite(productId);

    if (willBeFavorite) {
      try {
        ReactPixel.track('AddToWishlist', {
          content_ids: [productId],
          contents: [{ id: productId, quantity: 1 }],
          value: productPrice || 0,
          currency: 'EGP',
        });
      } catch {
        // Safe fail if Pixel is not initialized
      }
    }
  };

  return (
    <Button
      size={size}
      variant={variant}
      className={`transition-all backdrop-blur-sm shadow-sm ${
        active ? 'text-red-500 hover:text-red-600' : 'text-muted-foreground hover:text-foreground'
      } ${className}`}
      onClick={handleClick}
      aria-label={active ? 'Remove from favorites' : 'Add to favorites'}
    >
      <Heart className={`h-4 w-4 transition-transform ${active ? 'fill-current scale-110' : ''}`} />
    </Button>
  );
}
