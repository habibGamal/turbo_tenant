import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { HeartOff, ArrowRight } from 'lucide-react';
import { useCustomerFavorites } from '@/plugins/customer-favorites';
import ProductCard from '@/themes/default/plugins/catalog-products/components/ProductCard';
import { FavoritesSkeleton } from '@/components/ui/page-skeletons';
import { Product } from '@/types';

export interface ThemedCustomerFavoritesProps {
  onExploreMenu?: () => void;
}

export default function ThemedCustomerFavorites({
  onExploreMenu,
}: ThemedCustomerFavoritesProps) {
  const { t } = useTranslation();
  const { products, isLoading, totalCount } = useCustomerFavorites();

  const sortedProducts = React.useMemo(() => {
    const available: Product[] = [];
    const unavailable: Product[] = [];
    for (const p of products) {
      const isAvail = (p.is_active ?? true) && (p as any).is_available !== false;
      if (isAvail) {
        available.push(p as unknown as Product);
      } else {
        unavailable.push(p as unknown as Product);
      }
    }
    return [...available, ...unavailable];
  }, [products]);

  if (isLoading) {
    return <FavoritesSkeleton />;
  }

  return (
    <div data-testid="favorites-page" className="container mx-auto py-10 px-4 md:px-6">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{t('myFavorites', 'My Favorites')}</h1>
          {totalCount > 0 && (
            <p className="text-sm text-muted-foreground mt-1">
              {totalCount} {totalCount === 1 ? t('item', 'item') : t('items', 'items')}
            </p>
          )}
        </div>
      </div>

      {sortedProducts.length > 0 ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {sortedProducts.map((product) => (
            <ProductCard
              key={product.id}
              product={product}
              showFavorite={true}
            />
          ))}
        </div>
      ) : (
        <div className="flex flex-col items-center justify-center h-80 text-center border border-border rounded-2xl bg-muted/70 dark:bg-black/25 shadow-none p-8">
          <div className="p-4 rounded-full border border-border/80 bg-background dark:bg-card mb-4">
            <HeartOff className="h-12 w-12 text-muted-foreground" />
          </div>
          <h2 className="text-xl font-semibold mb-2">
            {t('noFavoritesYet', 'No favorites yet')}
          </h2>
          <p className="text-muted-foreground max-w-sm mb-6">
            {t('startAddingFavorites', 'Start adding items to your favorites to quickly find them later.')}
          </p>
          <Link
            to="/menu"
            onClick={(e) => {
              if (onExploreMenu) {
                e.preventDefault();
                onExploreMenu();
              }
            }}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary text-primary-foreground font-medium hover:bg-primary/90 transition-colors shadow-none"
          >
            {t('browseMenu', 'Browse Menu')}
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          </Link>
        </div>
      )}
    </div>
  );
}
