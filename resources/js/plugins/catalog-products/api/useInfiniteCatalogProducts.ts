import { useInfiniteQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { Paginated, PaginationMeta } from '@/core/api/types';
import { ProductSummary } from '../types';

export interface CatalogProductsPage {
  data: ProductSummary[];
  meta: PaginationMeta;
}

export function useInfiniteCatalogProducts(params?: Record<string, any>) {
  return useInfiniteQuery<CatalogProductsPage>({
    queryKey: ['catalog-products-infinite', params],
    queryFn: async ({ pageParam = 1 }) => {
      const { data } = await apiClient.get<Paginated<ProductSummary>>('/api/catalog-products', {
        params: {
          ...params,
          page: pageParam,
        },
      });
      return {
        data: data.data,
        meta: data.meta,
      };
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
