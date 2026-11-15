import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { ProductSummary } from '../types';

export function useCatalogSearch(query: string, limit: number = 5) {
  const trimmed = query.trim();
  return useQuery<ProductSummary[]>({
    queryKey: ['catalog-products', 'search', trimmed, limit],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiSuccess<ProductSummary[]>>('/api/catalog-products/suggestions', {
        params: { q: trimmed, limit },
      });
      return data.data;
    },
    enabled: trimmed.length >= 2,
    staleTime: 1000 * 30, // 30 seconds
  });
}
