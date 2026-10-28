import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ProductDetailResponse } from '../types';

export function useCatalogProduct(id: number | string | undefined | null) {
  return useQuery<ProductDetailResponse>({
    queryKey: ['catalog-products', 'detail', id],
    queryFn: async () => {
      const { data } = await apiClient.get<ProductDetailResponse>(`/api/catalog-products/${id}`);
      return data;
    },
    enabled: Boolean(id),
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}
