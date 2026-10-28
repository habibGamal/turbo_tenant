import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ProductReviewsResponse } from '../types';

export function useProductReviews(productId?: string | number) {
  return useQuery<ProductReviewsResponse>({
    queryKey: ['product-reviews', productId],
    queryFn: async () => {
      if (!productId) {
        throw new Error('Product ID is required to fetch reviews');
      }
      const { data } = await apiClient.get(`/products/${productId}/reviews`);
      return data;
    },
    enabled: !!productId,
    staleTime: 1000 * 60 * 2,
  });
}
