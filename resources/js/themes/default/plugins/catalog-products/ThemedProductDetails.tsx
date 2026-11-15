import React from 'react';
import { useCatalogProduct } from '@/plugins/catalog-products/api/useCatalogProduct';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Info, CheckCircle2, XCircle } from 'lucide-react';

export interface ThemedProductDetailsProps {
  productId?: number | string;
}

export default function ThemedProductDetails({ productId }: ThemedProductDetailsProps) {
  const { t, i18n } = useTranslation();
  const { data: product, isLoading } = useCatalogProduct(productId);

  if (isLoading || !product) {
    return null;
  }
  const isRTL = i18n.language === 'ar';
  const isAvailable = (product.is_active ?? true) && (product as any).is_available !== false;

  return (
    <div className="themed-catalog-product my-8">
      <Card className="border border-border/60 bg-muted/20">
        <CardHeader className="pb-3">
          <CardTitle className="text-lg flex items-center gap-2">
            <Info className="h-4 w-4 text-primary" />
            {t('productDetails', { defaultValue: 'Additional Information' })}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            {product.category && (
              <div>
                <span className="text-muted-foreground block text-xs">
                  {t('category', { defaultValue: 'Category' })}
                </span>
                <span className="font-medium">
                  {isRTL && product.category.name_ar ? product.category.name_ar : product.category.name}
                </span>
              </div>
            )}
            <div>
              <span className="text-muted-foreground block text-xs">
                {t('availability', { defaultValue: 'Availability' })}
              </span>
              {isAvailable ? (
                <span className="font-medium flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                  <CheckCircle2 className="h-3.5 w-3.5" />
                  {t('inStock', { defaultValue: 'In Stock' })}
                </span>
              ) : (
                <span className="font-medium flex items-center gap-1 text-destructive">
                  <XCircle className="h-3.5 w-3.5" />
                  {t('unavailable', { defaultValue: 'Unavailable' })}
                </span>
              )}
            </div>
            {(product as any).sku && (
              <div>
                <span className="text-muted-foreground block text-xs">SKU</span>
                <span className="font-mono text-xs">{(product as any).sku}</span>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
