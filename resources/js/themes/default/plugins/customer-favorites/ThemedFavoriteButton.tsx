import React from 'react';
import ReactPixel from 'react-facebook-pixel';
import { Heart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { useFavoritesStorage } from '@/plugins/customer-favorites';
import { useCurrency } from '@/lib/money';

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
  const { t } = useTranslation();
  const currency = useCurrency();
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
          currency,
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
        active ? 'text-destructive hover:text-destructive/90' : 'text-muted-foreground hover:text-foreground'
      } ${className}`}
      onClick={handleClick}
      aria-label={active ? t('removeFromFavorites', 'Remove from favorites') : t('addToFavorites', 'Add to favorites')}
      aria-pressed={active}
    >
      <Heart className={`h-4 w-4 transition-transform ${active ? 'fill-current scale-110' : ''}`} />
    </Button>
  );
}
