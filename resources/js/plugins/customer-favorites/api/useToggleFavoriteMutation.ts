import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { useFavoritesStorage } from './useFavoritesStorage';

export function useToggleFavoriteMutation() {
  const queryClient = useQueryClient();
  const { toggleFavorite } = useFavoritesStorage();

  return useMutation({
    mutationFn: async (productId: number) => {
      // 1. Immediately toggle in local storage for instant feedback
      toggleFavorite(productId);

      // 2. Notify backend endpoint
      const { data } = await apiClient.post('/api/customer-favorites', {
        product_id: productId,
      });
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-favorites'] });
    },
  });
}
