import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { ProductDetail } from '../types';

export async function fetchCatalogProduct(id: number | string): Promise<ProductDetail> {
  const { data } = await apiClient.get<ApiSuccess<ProductDetail>>(`/api/catalog-products/${id}`);
  return data.data;
}

export function useCatalogProduct(id: number | string | undefined | null) {
  return useQuery<ProductDetail>({
    queryKey: ['catalog-products', 'detail', id],
    queryFn: async () => {
      if (!id) throw new Error('Product ID is required');
      return fetchCatalogProduct(id);
    },
    enabled: Boolean(id),
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}
