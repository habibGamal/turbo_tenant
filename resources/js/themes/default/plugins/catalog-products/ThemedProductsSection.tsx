import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCatalogSections } from '@/plugins/catalog-products/api/useCatalogSections';
import { useCatalogCategories } from '@/plugins/catalog-products/api/useCatalogCategories';
import { Section, ProductSummary, Category } from '@/plugins/catalog-products/types';
import { Button } from '@/components/ui/button';
import { Star, TrendingUp, Flame } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import ProductCard from './components/ProductCard';
import { ImageWithFallback } from '@/components/ui/image';
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
  const { data: sections, isLoading } = useCatalogSections();
  const { data: categoriesData, isLoading: categoriesLoading } = useCatalogCategories();
  const { mutate: addToCartMutation } = useAddToCart();
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [addingToCart, setAddingToCart] = useState<Record<number, boolean>>({});

  const sectionsToUse: Section[] = propSections || sections || [];
  const categories: Category[] = categoriesData || [];

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

  if ((isLoading && sectionsToUse.length === 0) || categoriesLoading) {
    return <CatalogSectionsSkeleton />;
  }

  if (sectionsToUse.length === 0) {
    return null;
  }

  return (
    <>
      {/* Category Navigation Tabs */}
      {categories.length > 0 && (
        <section className="py-8 bg-background border-b">
          <div className="container mx-auto px-4">
            <div className="flex items-start gap-4 sm:gap-6 overflow-x-auto pb-2 scrollbar-none" role="tablist">
              {categories.map((cat, index) => {
                const isSelected = selectedCategory === cat.id;
                return (
                  <button
                    key={cat.id}
                    data-testid="category-tab"
                    role="tab"
                    aria-selected={isSelected}
                    onClick={() => setSelectedCategory(isSelected ? null : cat.id)}
                    className={`group flex w-24 sm:w-32 shrink-0 flex-col items-center gap-2 rounded-xl border border-transparent p-1 text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${isSelected ? "border-primary" : ""}`}
                  >
                    <span className={`block w-full aspect-square overflow-hidden rounded-2xl border-2 transition-colors ${isSelected ? 'border-primary' : 'border-transparent group-hover:border-primary/50'}`}>
                      <ImageWithFallback
                        src={cat.image}
                        alt={i18n.language === 'ar' && cat.name_ar ? cat.name_ar : cat.name}
                        className="h-full w-full object-cover"
                        priority={index === 0}
                        loading={index < 2 ? "eager" : "lazy"}
                      />
                    </span>
                    <span className={`text-sm font-semibold ${isSelected ? 'text-primary' : 'text-foreground'}`}>
                      {i18n.language === 'ar' && cat.name_ar ? cat.name_ar : cat.name}
                    </span>
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
              <Button asChild variant="outline">
                <Link to="/menu" data-testid="section-view-all">
                  {t('viewAll')}
                </Link>
              </Button>
            </div>

            {/* Products Grid */}
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {pushUnavailableToEnd(section.products).map((product: ProductSummary, productIndex: number) => (
                <ProductCard
                  key={product.id}
                  product={product as any}
                  onAddToCart={handleAddToCart}
                  addingToCart={addingToCart[product.id]}
                  showFavorite={true}
                  priority={sectionIndex === 0 && productIndex < 2}
                />
              ))}
            </div>
          </div>
        </section>
      ))}
    </>
  );
}
