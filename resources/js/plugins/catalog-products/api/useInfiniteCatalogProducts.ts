import { useInfiniteQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { CatalogProductsResponse } from '../types';

export function useInfiniteCatalogProducts(params?: Record<string, any>) {
  return useInfiniteQuery<CatalogProductsResponse>({
    queryKey: ['catalog-products-infinite', params],
    queryFn: async ({ pageParam = 1 }) => {
      const { data } = await apiClient.get<CatalogProductsResponse>('/api/catalog-products', {
        params: {
          ...params,
          page: pageParam,
        },
      });
      return data;
    },
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      if (lastPage?.meta && lastPage.meta.current_page < lastPage.meta.last_page) {
        return lastPage.meta.current_page + 1;
      }
      return undefined;
    },
    staleTime: 1000 * 60 * 2,
  });
}
