import React, { createContext, useContext, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';

export interface TenantInfo {
  id: string;
  name: string;
  theme: string;
}

export interface BootstrapResponse {
  tenant: TenantInfo | null;
  user: {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
  } | null;
  settings: Record<string, any>;
  active_plugins: string[];
  cart_count: number;
  is_dev_mode: boolean;
}

interface TenantContextType {
  tenant: TenantInfo | null;
  settings: Record<string, any>;
  activePlugins: string[];
  cartCount: number;
  isLoading: boolean;
  isDevMode: boolean;
  hasPlugin: (pluginId: string) => boolean;
  refetchBootstrap: () => void;
}

const TenantContext = createContext<TenantContextType | undefined>(undefined);

export function TenantProvider({ children }: { children: React.ReactNode }) {
  const { data, isLoading, refetch } = useQuery<BootstrapResponse>({
    queryKey: ['tenant', 'bootstrap'],
    queryFn: async () => {
      const res = await apiClient.get<BootstrapResponse>('/api/tenant/bootstrap');
      return res.data;
    },
    staleTime: 1000 * 60 * 5, // 5 minutes
  });

  const tenant = data?.tenant || null;
  const settings = data?.settings || {};
  const activePlugins = data?.active_plugins || [];
  const cartCount = data?.cart_count ?? 0;
  const isDevMode = data?.is_dev_mode ?? false;

  const hasPlugin = (pluginId: string): boolean => {
    return activePlugins.includes(pluginId);
  };

  useEffect(() => {
    if (settings.site_name) {
      document.title = settings.site_name;
    }

  }, [settings.site_name]);

  return (
    <TenantContext.Provider
      value={{
        tenant,
        settings,
        activePlugins,
        cartCount,
        isLoading,
        isDevMode,
        hasPlugin,
        refetchBootstrap: refetch,
      }}
    >
      {children}
    </TenantContext.Provider>
  );
}

export function useTenant(): TenantContextType {
  const context = useContext(TenantContext);
  if (!context) {
    throw new Error('useTenant must be used within a TenantProvider');
  }
  return context;
}

export function useTenantSafe(): TenantContextType | null {
  return useContext(TenantContext) || null;
}
