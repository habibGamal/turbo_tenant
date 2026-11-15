import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { ProductSummary } from '../types';

export function useCatalogProductsByIds(ids: Array<number | string>) {
  const sortedIds = [...ids].sort();
  return useQuery<ProductSummary[]>({
    queryKey: ['catalog-products', 'by-ids', sortedIds],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiSuccess<ProductSummary[]>>('/api/catalog-products/by-ids', {
        params: { ids },
      });
      return data.data;
    },
    enabled: ids.length > 0,
    staleTime: 1000 * 60 * 5,
  });
}
