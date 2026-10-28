import React from 'react';
import { useFavoritesStorage } from '../api/useFavoritesStorage';

export interface DefaultFavoriteButtonProps {
  productId: number;
  className?: string;
  activeClassName?: string;
  inactiveClassName?: string;
  onToggled?: (isFavorite: boolean) => void;
}

export default function DefaultFavoriteButton({
  productId,
  className = 'p-2 rounded-full border transition-colors',
  activeClassName = 'bg-red-50 text-red-600 border-red-200',
  inactiveClassName = 'bg-background text-muted-foreground hover:text-foreground',
  onToggled,
}: DefaultFavoriteButtonProps) {
  const { isFavorite, toggleFavorite } = useFavoritesStorage();
  const active = isFavorite(productId);

  const handleClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    toggleFavorite(productId);
    onToggled?.(!active);
  };

  return (
    <button
      type="button"
      onClick={handleClick}
      aria-label={active ? 'Remove from favorites' : 'Add to favorites'}
      className={`${className} ${active ? activeClassName : inactiveClassName}`}
    >
      <span className="text-base leading-none">
        {active ? '❤️' : '🤍'}
      </span>
    </button>
  );
}
