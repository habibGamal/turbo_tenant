import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Utensils, Sparkles, Clock } from 'lucide-react';
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog';
import { useFormatMoney } from '@/lib/money';
import { ComboPackage } from '../types';
import {
  getLocalizedName,
  getLocalizedDescription,
  getLocalizedBadge,
  savingsLabelData,
  endsSoon,
  summarizeContents,
  formatAvailability,
  orderTypeLabel,
  availabilityBadge,
} from '../lib/packageDisplay';
import DefaultPackageConfigurator from './DefaultPackageConfigurator';

interface DefaultPackageCardProps {
  packageItem: ComboPackage;
}

export default function DefaultPackageCard({ packageItem }: DefaultPackageCardProps) {
  const { t, i18n } = useTranslation();
  const formatMoney = useFormatMoney();
  const [isConfiguratorOpen, setIsConfiguratorOpen] = useState(false);

  const name = getLocalizedName(packageItem, i18n.language);
  const description = getLocalizedDescription(packageItem, i18n.language);
  const badgeText = getLocalizedBadge(packageItem, i18n.language) || t('combo', 'Combo');
  const savings = savingsLabelData(packageItem);
  const isEndingSoon = endsSoon(packageItem);
  const contents = summarizeContents(packageItem, i18n.language);
  const isAvailable = packageItem.available_now !== false;
  const availLabel = formatAvailability(packageItem, i18n.language);
  const orderType = orderTypeLabel(packageItem, i18n.language);
  const badge = availabilityBadge(packageItem, undefined, i18n.language);

  return (
    <div
      data-testid="combo-package-card"
      className="border rounded-xl p-4 bg-card text-card-foreground shadow-sm flex flex-col justify-between group transition-shadow hover:shadow-md"
    >
      <div>
        <div className="relative aspect-video w-full overflow-hidden rounded-lg bg-muted mb-3">
          {packageItem.image ? (
            <img
              src={packageItem.image}
              alt={name}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-muted-foreground">
              <Utensils className="h-10 w-10 opacity-30" />
            </div>
          )}

          {packageItem.is_featured && (
            <div className="absolute top-2 start-2">
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-amber-500 text-amber-950 shadow-sm">
                <Sparkles className="h-3 w-3" />
                {t('featured', 'Featured')}
              </span>
            </div>
          )}

          <div className="absolute top-2 end-2 flex flex-col gap-1 items-end">
            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary text-primary-foreground shadow-sm">
              {badgeText}
            </span>
            {isEndingSoon && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-destructive text-destructive-foreground shadow-sm">
                <Clock className="h-3 w-3" />
                {t('endsSoon', 'Ends soon')}
              </span>
            )}
          </div>
        </div>

        <div>
          <Link to={`/packages/${packageItem.id}`}>
            <h4 data-testid="combo-package-title" className="font-bold text-base mb-1 hover:text-primary transition-colors cursor-pointer">
              {name}
            </h4>
          </Link>
          {description && (
            <p className="text-xs text-muted-foreground line-clamp-2 mb-2">
              {description}
            </p>
          )}

          {/* Availability and Dayparts */}
          {(availLabel || (!isAvailable && badge.kind === 'outside_hours')) && (
            <div
              data-testid="combo-package-availability"
              className="flex items-center gap-1.5 text-xs text-muted-foreground mb-2"
            >
              <Clock className="w-3.5 h-3.5 shrink-0" />
              <span>
                {!isAvailable && badge.kind === 'outside_hours'
                  ? (availLabel ? `${availLabel} • ${badge.text}` : badge.text)
                  : availLabel}
              </span>
            </div>
          )}

          {/* Order Type and Stock Badges */}
          {(orderType || badge.kind === 'sold_out' || badge.kind === 'few_left') && (
            <div className="flex flex-wrap items-center gap-1.5 mb-3">
              {orderType && (
                <span
                  data-testid="combo-package-order-type"
                  className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-secondary text-secondary-foreground"
                >
                  {orderType}
                </span>
              )}
              {badge.kind === 'sold_out' && (
                <span
                  data-testid="combo-package-stock"
                  className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-destructive/10 text-destructive border border-destructive/20"
                >
                  {badge.text}
                </span>
              )}
              {badge.kind === 'few_left' && (
                <span
                  data-testid="combo-package-stock"
                  className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20"
                >
                  {badge.text}
                </span>
              )}
            </div>
          )}

          {contents.length > 0 && (
            <div data-testid="combo-package-contents" className="space-y-1 mb-3">
              {contents.slice(0, 3).map((line, idx) => (
                <div key={idx} className="text-xs text-muted-foreground flex items-center gap-1.5">
                  <span className="inline-block w-1.5 h-1.5 rounded-full bg-primary/60 shrink-0" />
                  <span className="truncate">{line}</span>
                </div>
              ))}
              {contents.length > 3 && (
                <div className="text-xs text-muted-foreground font-medium ps-3">
                  {t('andMore', 'and more')}
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="mt-3 pt-3 border-t">
        <div className="flex items-baseline justify-between gap-2 mb-3">
          <div>
            <div className="flex items-baseline gap-2 flex-wrap">
              <span data-testid="combo-package-price" className="font-bold text-base text-primary">
                {formatMoney(packageItem.price)}
              </span>
              {savings && (
                <span data-testid="combo-package-original-price" className="text-xs text-muted-foreground line-through">
                  {formatMoney(savings.original)}
                </span>
              )}
            </div>
          </div>
          {savings && (
            <span data-testid="combo-package-savings" className="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
              {t('saveAmount', 'Save {{amount}}', { amount: formatMoney(savings.savings) })}
            </span>
          )}
        </div>

        {/* Unavailable reason explanatory text when !available_now */}
        {!isAvailable && (
          <p className="text-xs text-destructive font-medium mb-2 text-center">
            {badge.text || t('packageUnavailable', 'This combo package is currently unavailable.')}
          </p>
        )}

        <Dialog open={isConfiguratorOpen && isAvailable} onOpenChange={(open) => isAvailable && setIsConfiguratorOpen(open)}>
          <DialogTrigger asChild>
            <button
              type="button"
              data-testid="combo-add-to-cart"
              disabled={!isAvailable}
              title={!isAvailable ? (badge.text || t('packageUnavailable', 'This combo package is currently unavailable.')) : undefined}
              className="w-full px-3 py-1.5 text-xs font-medium bg-primary text-primary-foreground rounded-md transition-colors hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {t('selectPackage', 'Select Package')}
            </button>
          </DialogTrigger>
          {isAvailable && (
            <DialogContent className="max-w-2xl p-0 overflow-hidden sm:max-h-[85vh]">
              <DefaultPackageConfigurator
                packageData={packageItem}
                onClose={() => setIsConfiguratorOpen(false)}
              />
            </DialogContent>
          )}
        </Dialog>
      </div>
    </div>
  );
}
