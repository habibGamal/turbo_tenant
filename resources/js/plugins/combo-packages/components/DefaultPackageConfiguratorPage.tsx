import React from 'react';
import { useParams, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Utensils, XCircle } from 'lucide-react';
import { usePackage } from '../api/usePackage';
import {
  getLocalizedName,
  getLocalizedDescription,
} from '../lib/packageDisplay';
import DefaultPackageConfigurator from './DefaultPackageConfigurator';

export interface DefaultPackageConfiguratorPageProps {
  packageId?: string | number;
}

export default function DefaultPackageConfiguratorPage({
  packageId: propPackageId,
}: DefaultPackageConfiguratorPageProps) {
  const { id: paramId } = useParams<{ id: string }>();
  const packageId = propPackageId || paramId;

  const { t, i18n } = useTranslation();
  const { data: packageData, isLoading, error } = usePackage(packageId);

  if (isLoading) {
    return (
      <div className="max-w-4xl mx-auto p-8 text-center text-sm text-muted-foreground animate-pulse">
        {t('loading', 'Loading...')}
      </div>
    );
  }

  if (error || !packageData) {
    return (
      <div className="max-w-md mx-auto p-8 text-center">
        <XCircle className="w-12 h-12 text-destructive mx-auto mb-4" />
        <h2 className="text-xl font-bold mb-2">{t('packageNotFound', 'Package not found')}</h2>
        <Link to="/" className="text-sm text-primary hover:underline">
          {t('backToHome', 'Back to Home')}
        </Link>
      </div>
    );
  }

  const name = getLocalizedName(packageData, i18n.language);

  return (
    <div className="max-w-4xl mx-auto p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <Link to="/" className="hover:underline">
          {t('home', 'Home')}
        </Link>
        <span>/</span>
        <span className="font-medium text-foreground">{name}</span>
      </div>

      <div className="grid md:grid-cols-12 gap-6">
        <div className="md:col-span-5 space-y-4">
          <div className="aspect-video w-full rounded-lg bg-muted overflow-hidden">
            {packageData.image ? (
              <img src={packageData.image} alt={name} className="w-full h-full object-cover" />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-muted-foreground">
                <Utensils className="w-12 h-12 opacity-30" />
              </div>
            )}
          </div>
          <div>
            <h1 className="text-2xl font-bold">{name}</h1>
            {packageData.description && (
              <p className="text-xs text-muted-foreground mt-1">
                {getLocalizedDescription(packageData, i18n.language)}
              </p>
            )}
          </div>
        </div>

        <div className="md:col-span-7 border rounded-lg overflow-hidden bg-card">
          <DefaultPackageConfigurator packageData={packageData} />
        </div>
      </div>
    </div>
  );
}
