import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { Paginated, PaginationMeta } from '@/core/api/types';
import { ProductSummary } from '../types';

export interface CatalogProductsResult {
  data: ProductSummary[];
  meta: PaginationMeta;
}

export function useCatalogProducts(params?: Record<string, any>) {
  return useQuery<CatalogProductsResult>({
    queryKey: ['catalog-products', params],
    queryFn: async () => {
      const { data } = await apiClient.get<Paginated<ProductSummary>>('/api/catalog-products', { params });
      return {
        data: data.data,
        meta: data.meta,
      };
    },
    staleTime: 1000 * 60 * 5,
  });
}
