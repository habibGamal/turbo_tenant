import React from 'react';
import { Heart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation();
  const { isFavorite, toggleFavorite } = useFavoritesStorage();
  const active = isFavorite(productId);

  const handleClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();

    toggleFavorite(productId, productPrice);
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
