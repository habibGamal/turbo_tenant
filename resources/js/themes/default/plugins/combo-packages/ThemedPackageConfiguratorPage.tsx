import React from 'react';
import { useParams, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Utensils, XCircle, ArrowLeft, ArrowRight, Sparkles } from 'lucide-react';
import { Head } from '@/components/Head';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { usePackage } from '@/plugins/combo-packages/api/usePackage';
import {
  getLocalizedName,
  getLocalizedDescription,
  getLocalizedBadge,
} from '@/plugins/combo-packages/lib/packageDisplay';
import ThemedPackageConfigurator from './components/ThemedPackageConfigurator';

export interface ThemedPackageConfiguratorPageProps {
  packageId?: string | number;
}

export default function ThemedPackageConfiguratorPage({
  packageId: propPackageId,
}: ThemedPackageConfiguratorPageProps) {
  const { id: paramId } = useParams<{ id: string }>();
  const packageId = propPackageId || paramId;

  const { t, i18n } = useTranslation();
  const isRTL = i18n.language === 'ar' || i18n.language?.startsWith('ar');
  const BackArrow = isRTL ? ArrowRight : ArrowLeft;

  const { data: packageData, isLoading, error } = usePackage(packageId);

  if (isLoading) {
    return (
      <div className="container mx-auto px-4 py-12 max-w-4xl animate-pulse">
        <div className="h-6 w-32 bg-muted rounded mb-6" />
        <div className="grid md:grid-cols-2 gap-8">
          <div className="aspect-video bg-muted rounded-xl" />
          <div className="space-y-4">
            <div className="h-8 w-3/4 bg-muted rounded" />
            <div className="h-4 w-full bg-muted rounded" />
            <div className="h-32 bg-muted rounded" />
          </div>
        </div>
      </div>
    );
  }

  if (error || !packageData) {
    return (
      <div className="container mx-auto px-4 py-24 text-center max-w-md">
        <XCircle className="w-12 h-12 text-destructive mx-auto mb-4" />
        <h2 className="text-xl font-bold mb-2">
          {t('packageNotFound', 'Package not found')}
        </h2>
        <p className="text-sm text-muted-foreground mb-6">
          {t('packageNotFoundDesc', 'The requested combo package is either unavailable or has expired.')}
        </p>
        <Link to="/">
          <Button variant="outline" className="gap-2">
            <BackArrow className="w-4 h-4" />
            {t('backToHome', 'Back to Home')}
          </Button>
        </Link>
      </div>
    );
  }

  const name = getLocalizedName(packageData, i18n.language);
  const badgeText = getLocalizedBadge(packageData, i18n.language) || t('combo', 'Combo');

  return (
    <div className="min-h-screen bg-background pb-16">
      <Head title={name} />

      <main className="container mx-auto px-4 py-6 max-w-4xl">
        {/* Breadcrumb */}
        <div className="flex items-center gap-2 text-xs text-muted-foreground mb-6">
          <Link to="/" className="hover:text-foreground transition-colors">
            {t('home', 'Home')}
          </Link>
          <span>/</span>
          <span className="text-foreground font-medium truncate">{name}</span>
        </div>

        <div className="grid md:grid-cols-12 gap-8">
          {/* Left Column: Image and details */}
          <div className="md:col-span-5 space-y-4">
            <div className="relative aspect-video md:aspect-square w-full overflow-hidden rounded-xl bg-muted border">
              {packageData.image ? (
                <img
                  src={packageData.image}
                  alt={name}
                  className="w-full h-full object-cover"
                />
              ) : (
                <div className="w-full h-full flex items-center justify-center text-muted-foreground">
                  <Utensils className="w-16 h-16 opacity-30" />
                </div>
              )}

              <div className="absolute top-3 start-3 flex gap-2">
                <Badge className="shadow-md">{badgeText}</Badge>
                {packageData.is_featured && (
                  <Badge variant="secondary" className="bg-amber-500 text-amber-950 font-semibold shadow-md flex items-center gap-1">
                    <Sparkles className="w-3 h-3" />
                    {t('featured', 'Featured')}
                  </Badge>
                )}
              </div>
            </div>

            <div>
              <h1 className="text-2xl font-bold tracking-tight mb-2">{name}</h1>
              {packageData.description && (
                <p className="text-sm text-muted-foreground leading-relaxed">
                  {getLocalizedDescription(packageData, i18n.language)}
                </p>
              )}
            </div>
          </div>

          {/* Right Column: Configurator */}
          <div className="md:col-span-7 border rounded-xl overflow-hidden shadow-sm bg-card">
            <ThemedPackageConfigurator packageData={packageData} />
          </div>
        </div>
      </main>
    </div>
  );
}
