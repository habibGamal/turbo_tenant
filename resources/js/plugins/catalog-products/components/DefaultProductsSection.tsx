import React from 'react';
import { useCatalogSections } from '../api/useCatalogSections';
import { Section, ProductSummary } from '../types';

export interface DefaultProductsSectionProps {
  onProductClick?: (product: ProductSummary) => void;
}

export default function DefaultProductsSection({ onProductClick }: DefaultProductsSectionProps) {
  const { data, isLoading, error } = useCatalogSections();

  if (isLoading) {
    return (
      <div className="w-full py-8 text-center text-sm text-muted-foreground animate-pulse">
        Loading product sections...
      </div>
    );
  }

  if (error || !data || !data.data || data.data.length === 0) {
    return null;
  }

  return (
    <div className="w-full space-y-8 my-6">
      {data.data.map((section: Section) => (
        <section key={section.id} className="p-4 border rounded-lg bg-card text-card-foreground">
          <div className="flex items-center justify-between mb-4 border-b pb-2">
            <h3 className="font-semibold text-lg">{section.title}</h3>
            {section.title_ar && (
              <span className="text-sm text-muted-foreground" dir="rtl">{section.title_ar}</span>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {section.products.map((product: ProductSummary) => (
              <div
                key={product.id}
                onClick={() => onProductClick?.(product)}
                className="p-3 border rounded-md hover:shadow-sm transition-shadow flex flex-col justify-between cursor-pointer"
              >
                {product.image && (
                  <img
                    src={product.image}
                    alt={product.name}
                    className="w-full h-36 object-cover rounded mb-2 bg-muted"
                    loading="lazy"
                  />
                )}
                <div>
                  <h4 className="font-medium text-sm line-clamp-1">{product.name}</h4>
                  {product.description && (
                    <p className="text-xs text-muted-foreground line-clamp-2 mt-1">{product.description}</p>
                  )}
                </div>
                <div className="mt-3 flex items-baseline justify-between">
                  <span className="font-bold text-sm">
                    {product.price.toFixed(2)}
                  </span>
                  {product.price_after_discount && (
                    <span className="text-xs line-through text-muted-foreground">
                      {product.base_price.toFixed(2)}
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}
