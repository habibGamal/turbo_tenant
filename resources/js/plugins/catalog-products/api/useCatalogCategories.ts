import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Category } from '../types';

export function useCatalogCategories() {
  return useQuery<Category[]>({
    queryKey: ['catalog-products', 'categories'],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiSuccess<Category[]>>('/api/catalog-products/categories');
      return data.data;
    },
    staleTime: 1000 * 60 * 10, // 10 minutes
  });
}
