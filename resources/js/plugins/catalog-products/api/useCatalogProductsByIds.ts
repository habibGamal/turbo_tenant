import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ProductSummary } from '../types';

export function useCatalogProductsByIds(ids: Array<number | string>) {
  const sortedIds = [...ids].sort();
  return useQuery<{ status: string; data: ProductSummary[] }>({
    queryKey: ['catalog-products', 'by-ids', sortedIds],
    queryFn: async () => {
      const { data } = await apiClient.get<{ status: string; data: ProductSummary[] }>('/api/catalog-products/by-ids', {
        params: { ids },
      });
      return data;
    },
    enabled: ids.length > 0,
    staleTime: 1000 * 60 * 5,
  });
}
