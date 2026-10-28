import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { SectionsResponse } from '../types';

export function useCatalogSections() {
  return useQuery<SectionsResponse>({
    queryKey: ['catalog-products', 'sections'],
    queryFn: async () => {
      const { data } = await apiClient.get<SectionsResponse>('/api/catalog-products/sections');
      return data;
    },
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}
