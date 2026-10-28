import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { useFavoritesStorage } from './useFavoritesStorage';
import { CustomerFavoriteProduct, CustomerFavoritesApiResponse } from '../types';

export interface UseCustomerFavoritesOptions {
  ids?: number[];
  enabled?: boolean;
}

export function useCustomerFavorites(options: UseCustomerFavoritesOptions = {}) {
  const { favoriteIds } = useFavoritesStorage();
  const targetIds = options.ids ?? favoriteIds;
  const isEnabled = options.enabled ?? true;

  const query = useQuery<CustomerFavoriteProduct[]>({
    queryKey: ['customer-favorites', targetIds],
    queryFn: async () => {
      if (!targetIds || targetIds.length === 0) {
        return [];
      }

      const { data } = await apiClient.get<CustomerFavoritesApiResponse>('/api/customer-favorites', {
        params: { ids: targetIds },
      });

      return data.data?.products ?? [];
    },
    staleTime: 1000 * 60 * 3, // 3 minutes cache
    enabled: isEnabled,
  });

  return {
    ...query,
    products: query.data ?? [],
    isEmpty: !query.isLoading && (query.data?.length === 0 || targetIds.length === 0),
    totalCount: targetIds.length,
  };
}
