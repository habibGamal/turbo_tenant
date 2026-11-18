import React from 'react';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/core/context/TenantContext';
import { useActiveTheme } from '@/core/hooks/useActiveTheme';

export interface RequirePluginProps {
  id: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function PageNotAvailable({ message }: { message?: string }) {
  const { t } = useTranslation();
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <div className="min-h-[50vh] flex flex-col items-center justify-center p-6 text-center">
        <h1 className="text-3xl font-bold tracking-tight mb-2">
          {message ?? t('pageNotAvailable', 'Page not available')}
        </h1>
        <p className="text-muted-foreground text-sm">
          {t('featureNotAvailableForStore', 'This feature is currently not available for this store.')}
        </p>
      </div>
    </MainLayout>
  );
}

export function RequirePlugin({ id, children, fallback }: RequirePluginProps): React.ReactElement | null {
  const { hasPlugin, isLoading } = useTenant();

  if (isLoading) {
    return null;
  }

  if (!hasPlugin(id)) {
    return fallback !== undefined ? <>{fallback}</> : <PageNotAvailable />;
  }

  return <>{children}</>;
}
