import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { CategoriesResponse } from '../types';

export function useCatalogCategories() {
  return useQuery<CategoriesResponse>({
    queryKey: ['catalog-products', 'categories'],
    queryFn: async () => {
      const { data } = await apiClient.get<CategoriesResponse>('/api/catalog-products/categories');
      return data;
    },
    staleTime: 1000 * 60 * 10, // 10 minutes
  });
}
