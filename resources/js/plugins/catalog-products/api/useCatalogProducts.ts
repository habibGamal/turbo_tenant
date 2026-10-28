import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { CatalogProductsResponse } from '../types';

export function useCatalogProducts(params?: Record<string, any>) {
  return useQuery<CatalogProductsResponse>({
    queryKey: ['catalog-products', params],
    queryFn: async () => {
      const { data } = await apiClient.get<CatalogProductsResponse>('/api/catalog-products', { params });
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });
}
