import React from 'react';
import { useTenant } from '@/core/context/TenantContext';
import { useActiveTheme } from '@/core/hooks/useActiveTheme';

export interface RequirePluginProps {
  id: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function PageNotAvailable({ message = 'Page not available' }: { message?: string }) {
  const { layouts: { MainLayout } } = useActiveTheme();
  return (
    <MainLayout>
      <div className="min-h-[50vh] flex flex-col items-center justify-center p-6 text-center">
        <h1 className="text-3xl font-bold tracking-tight mb-2">{message}</h1>
        <p className="text-muted-foreground text-sm">
          This feature is currently not available for this store.
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
