import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCatalogSections } from '@/plugins/catalog-products/api/useCatalogSections';
import { useCatalogCategories } from '@/plugins/catalog-products/api/useCatalogCategories';
import { Section, ProductSummary } from '@/plugins/catalog-products/types';
import { Button } from '@/components/ui/button';
import { Star, TrendingUp, Flame } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import ProductCard from './components/ProductCard';
import { CatalogSectionsSkeleton } from '@/components/ui/page-skeletons';
import { useAddToCart } from '@/plugins/cart-engine/api';

function pushUnavailableToEnd(products: ProductSummary[]): ProductSummary[] {
  const available: ProductSummary[] = [];
  const unavailable: ProductSummary[] = [];
  for (const product of products) {
    const isAvail = (product.is_active ?? true) && (product as any).is_available !== false;
    if (isAvail) {
      available.push(product);
    } else {
      unavailable.push(product);
    }
  }
  return [...available, ...unavailable];
}

export interface ThemedProductsSectionProps {
  sections?: Section[];
}

export default function ThemedProductsSection({ sections: propSections }: ThemedProductsSectionProps) {
  const { t, i18n } = useTranslation();
  const { data, isLoading } = useCatalogSections();
  const { data: categoriesData } = useCatalogCategories();
  const { mutate: addToCartMutation } = useAddToCart();
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [addingToCart, setAddingToCart] = useState<Record<number, boolean>>({});

  const sectionsToUse: Section[] = propSections || data?.data || [];
  const categories = categoriesData?.data || [];

  const displayedSections = selectedCategory
    ? sectionsToUse
        .map((s) => ({
          ...s,
          products: s.products.filter((p) => p.category?.id === selectedCategory),
        }))
        .filter((s) => s.products.length > 0)
    : sectionsToUse;

  const handleAddToCart = (e: React.MouseEvent, productId: number) => {
    e.preventDefault();
    e.stopPropagation();

    setAddingToCart((prev) => ({ ...prev, [productId]: true }));

    addToCartMutation(
      {
        product_id: productId,
        quantity: '1',
      },
      {
        onSettled: () => {
          setAddingToCart((prev) => ({ ...prev, [productId]: false }));
        },
        onError: () => {
          toast.error(t('failedToAddToCart', 'Failed to add to cart'));
        },
      }
    );
  };

  const getSectionIcon = (iconName?: string) => {
    const iconClass = 'h-5 w-5';
    switch (iconName) {
      case 'trending':
        return <TrendingUp className={iconClass} />;
      case 'flame':
        return <Flame className={iconClass} />;
      case 'star':
      default:
        return <Star className={iconClass} />;
    }
  };

  if (isLoading && sectionsToUse.length === 0) {
    return <CatalogSectionsSkeleton />;
  }

  if (sectionsToUse.length === 0) {
    return null;
  }

  return (
    <>
      {/* Category Navigation Tabs */}
      {categories.length > 0 && (
        <section className="py-6 bg-background border-b sticky top-0 z-20 backdrop-blur-md bg-background/80">
          <div className="container mx-auto px-4">
            <div className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none" role="tablist">
              {categories.map((cat) => {
                const isSelected = selectedCategory === cat.id;
                return (
                  <button
                    key={cat.id}
                    data-testid="category-tab"
                    role="tab"
                    aria-selected={isSelected}
                    onClick={() => setSelectedCategory(isSelected ? null : cat.id)}
                    className={`px-5 py-2.5 rounded-full text-sm font-semibold transition-all shrink-0 border ${
                      isSelected
                        ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                        : 'bg-card text-foreground hover:bg-muted border-border'
                    }`}
                  >
                    {i18n.language === 'ar' && cat.name_ar ? cat.name_ar : cat.name}
                  </button>
                );
              })}
            </div>
          </div>
        </section>
      )}

      {displayedSections.map((section, sectionIndex) => (
        <section
          key={section.id}
          data-testid="catalog-section"
          className={`py-12 md:py-20 ${
            sectionIndex % 2 === 0 ? 'bg-background' : 'bg-muted/30'
          }`}
        >
          <div className="container mx-auto px-4">
            {/* Section Header */}
            <div className="flex flex-row items-end justify-between mb-10 md:mb-14 gap-4">
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <div className="p-2 rounded-lg bg-primary/10">
                    <div className="text-primary">{getSectionIcon('star')}</div>
                  </div>
                  <h2 data-testid="section-title" className="text-3xl md:text-4xl font-bold tracking-tight">
                    {i18n.language === 'ar' && section.title_ar
                      ? section.title_ar
                      : section.title}
                  </h2>
                </div>
              </div>
              <Link to={`/sections/${section.id}`}>
                <Button data-testid="section-view-all" variant="outline" className="gap-2">
                  {t('View All')}
                </Button>
              </Link>
            </div>

            {/* Products Grid */}
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {pushUnavailableToEnd(section.products).map((product: ProductSummary) => (
                <ProductCard
                  key={product.id}
                  product={product as any}
                  onAddToCart={handleAddToCart}
                  addingToCart={addingToCart[product.id]}
                  showFavorite={true}
                />
              ))}
            </div>
          </div>
        </section>
      ))}
    </>
  );
}
