import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { SuggestionsResponse } from '../types';

export function useCatalogSearch(query: string, limit: number = 5) {
  const trimmed = query.trim();
  return useQuery<SuggestionsResponse>({
    queryKey: ['catalog-products', 'search', trimmed, limit],
    queryFn: async () => {
      const { data } = await apiClient.get<SuggestionsResponse>('/api/catalog-products/suggestions', {
        params: { q: trimmed, limit },
      });
      return data;
    },
    enabled: trimmed.length >= 2,
    staleTime: 1000 * 30, // 30 seconds
  });
}
