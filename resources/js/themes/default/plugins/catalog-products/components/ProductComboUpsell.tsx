import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Utensils, Sparkles, ArrowRight, ArrowLeft } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { useFormatMoney } from '@/lib/money';
import { ProductComboPackageUpsell } from '@/plugins/catalog-products/types';

export interface ProductComboUpsellProps {
  packages: ProductComboPackageUpsell[];
}

export function ProductComboUpsell({ packages }: ProductComboUpsellProps) {
  const { t, i18n } = useTranslation();
  const formatMoney = useFormatMoney();
  const isRTL = i18n.language === 'ar' || i18n.language?.startsWith('ar');
  const ArrowIcon = isRTL ? ArrowLeft : ArrowRight;

  if (!packages || packages.length === 0) {
    return null;
  }

  const displayedPackages = packages.slice(0, 3);

  return (
    <section data-testid="product-combo-upsell" className="my-10">
      <div className="flex items-center justify-between mb-4">
        <div>
          <div className="flex items-center gap-2">
            <Sparkles className="w-5 h-5 text-amber-500" />
            <h3 className="text-xl font-bold tracking-tight">
              {t('makeItACombo', 'Make it a combo')}
            </h3>
          </div>
          <p className="text-xs text-muted-foreground mt-0.5">
            {t('makeItAComboSubtitle', 'Upgrade your order with these recommended combo meals')}
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        {displayedPackages.map((pkg) => {
          const name = isRTL && pkg.name_ar ? pkg.name_ar : pkg.name;
          const hasDiscount =
            pkg.original_price != null && Number(pkg.original_price) > Number(pkg.price);

          return (
            <Link
              key={pkg.id}
              to={`/packages/${pkg.id}`}
              data-testid="product-combo-upsell-item"
              className="group block"
            >
              <Card className="overflow-hidden transition-colors duration-200 group-hover:border-primary/40 h-full flex flex-col justify-between">
                <div className="relative aspect-[16/9] w-full overflow-hidden bg-background dark:bg-card border-b border-border/60">
                  {pkg.image ? (
                    <img
                      src={pkg.image}
                      alt={name}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-muted-foreground">
                      <Utensils className="h-8 w-8 opacity-30" />
                    </div>
                  )}
                </div>

                <CardContent className="p-3.5 flex-1 flex flex-col justify-between">
                  <div>
                    <h4 className="font-semibold text-sm line-clamp-1 group-hover:text-primary transition-colors">
                      {name}
                    </h4>
                  </div>

                  <div className="mt-3 pt-2 border-t flex items-center justify-between">
                    <div className="flex items-baseline gap-1.5 flex-wrap">
                      <span className="font-bold text-sm text-primary">
                        {formatMoney(pkg.price)}
                      </span>
                      {hasDiscount && (
                        <span className="text-xs text-muted-foreground line-through">
                          {formatMoney(pkg.original_price!)}
                        </span>
                      )}
                    </div>

                    <span className="text-xs text-primary font-medium flex items-center gap-1 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5 transition-transform">
                      <ArrowIcon className="w-3.5 h-3.5" />
                    </span>
                  </div>
                </CardContent>
              </Card>
            </Link>
          );
        })}
      </div>
    </section>
  );
}

export default ProductComboUpsell;
