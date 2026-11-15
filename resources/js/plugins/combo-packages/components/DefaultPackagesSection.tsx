import React from 'react';
import { useTranslation } from 'react-i18next';
import { usePackages } from '../api/usePackages';
import DefaultPackageCard from './DefaultPackageCard';

export default function DefaultPackagesSection() {
  const { t } = useTranslation();
  const { data: packages, isLoading } = usePackages();

  const sortedPackages = React.useMemo(() => {
    if (!packages) return [];
    return [...packages].sort((a, b) => {
      if (a.is_featured !== b.is_featured) {
        return a.is_featured ? -1 : 1;
      }
      return (a.sort_order ?? 0) - (b.sort_order ?? 0);
    });
  }, [packages]);

  if (isLoading || !packages || packages.length === 0) {
    return null;
  }

  return (
    <section data-testid="combos-section" className="py-8 my-6">
      <div className="container mx-auto px-4">
        <h2 className="text-2xl font-bold mb-6">{t('comboPackages', 'Combo Packages & Meals')}</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {sortedPackages.map((pkg) => (
            <DefaultPackageCard key={pkg.id} packageItem={pkg} />
          ))}
        </div>
      </div>
    </section>
  );
}
