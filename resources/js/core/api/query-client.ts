import { QueryClient } from '@tanstack/react-query';
import type { BootstrapResponse } from '@/core/context/TenantContext';

declare global {
  interface Window {
    __TENANT_BOOTSTRAP__?: BootstrapResponse & { query_data?: { key: unknown[]; data: unknown }[] };
  }
}

/**
 * Pre-configured TanStack Query Client for Turbo Tenant.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 2, // 2 minutes cache validity
      gcTime: 1000 * 60 * 10,    // 10 minutes garbage collection
      retry: 1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 0,
    },
  },
});

// This payload belongs to this document's tenant and session; never persist it.
const bootstrap = window.__TENANT_BOOTSTRAP__;
if (bootstrap) {
  queryClient.setQueryData(['tenant', 'bootstrap'], bootstrap);
  queryClient.setQueryData(['auth', 'user'], { user: bootstrap.user });
  for (const query of bootstrap.query_data ?? []) {
    queryClient.setQueryData(query.key, query.data);
  }
  delete window.__TENANT_BOOTSTRAP__;
}
