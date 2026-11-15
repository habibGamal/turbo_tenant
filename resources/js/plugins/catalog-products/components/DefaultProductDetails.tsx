import React from 'react';
import { useCatalogProduct } from '../api/useCatalogProduct';
import { ProductDetail } from '../types';
import ProductComboUpsell from './ProductComboUpsell';

export interface DefaultProductDetailsProps {
  productId?: number | string;
  initialProduct?: ProductDetail;
  onAddToCart?: (payload: { productId: number; variantId?: number; quantity: number }) => void;
}

export default function DefaultProductDetails({
  productId,
  initialProduct,
  onAddToCart,
}: DefaultProductDetailsProps) {
  const { data: productData, isLoading, error } = useCatalogProduct(productId);
  const product = productData || initialProduct;

  if (isLoading && !product) {
    return (
      <div className="w-full py-8 text-center text-sm text-muted-foreground animate-pulse">
        Loading product details...
      </div>
    );
  }

  if (error || !product) {
    return null;
  }

  return (
    <div className="w-full max-w-4xl mx-auto my-6 p-6 border rounded-lg bg-card text-card-foreground">
      <div className="grid md:grid-cols-2 gap-6">
        {product.image && (
          <img
            src={product.image}
            alt={product.name}
            className="w-full h-72 object-cover rounded-lg bg-muted"
          />
        )}
        <div className="flex flex-col justify-between">
          <div>
            <h2 className="text-2xl font-bold">{product.name}</h2>
            {product.name_ar && (
              <p className="text-lg text-muted-foreground mt-1" dir="rtl">{product.name_ar}</p>
            )}
            {product.category && (
              <span className="inline-block px-2.5 py-0.5 mt-2 text-xs font-semibold rounded-full bg-secondary text-secondary-foreground">
                {product.category.name}
              </span>
            )}
            {product.description && (
              <p className="mt-4 text-sm text-muted-foreground leading-relaxed">
                {product.description}
              </p>
            )}

            <div className="mt-4 flex items-baseline gap-3">
              <span className="text-2xl font-extrabold">
                {product.price.toFixed(2)}
              </span>
              {product.price_after_discount && (
                <span className="text-sm line-through text-muted-foreground">
                  {product.base_price.toFixed(2)}
                </span>
              )}
            </div>

            {product.variants && product.variants.length > 0 && (
              <div className="mt-4">
                <label className="text-xs font-medium text-muted-foreground">Variants:</label>
                <div className="flex flex-wrap gap-2 mt-1">
                  {product.variants.map((v) => (
                    <span
                      key={v.id}
                      className="px-2 py-1 text-xs border rounded bg-background"
                    >
                      {v.name} ({v.price !== null ? v.price.toFixed(2) : ''})
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>

          <button
            type="button"
            onClick={() => onAddToCart?.({ productId: product.id, quantity: 1 })}
            className="w-full mt-6 py-2.5 px-4 rounded-md font-medium text-sm bg-primary text-primary-foreground hover:opacity-90 transition-opacity"
          >
            Add to Cart
          </button>
        </div>
      </div>

      {product.combo_packages && product.combo_packages.length > 0 && (
        <ProductComboUpsell packages={product.combo_packages} />
      )}
    </div>
  );
}
