import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Section } from '../types';

export function useCatalogSections() {
  return useQuery<Section[]>({
    queryKey: ['catalog-products', 'sections'],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiSuccess<Section[]>>('/api/catalog-products/sections');
      return data.data;
    },
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}
