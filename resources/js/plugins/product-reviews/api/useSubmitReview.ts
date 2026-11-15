import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ReviewItem, SingleReviewResponse, SubmitReviewPayload } from '../types';

export function useSubmitReview(productId: string | number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: SubmitReviewPayload): Promise<ReviewItem> => {
      const formData = new FormData();
      formData.append('rating', String(payload.rating));
      formData.append('comment', payload.comment);
      if (payload.guest_name) formData.append('guest_name', payload.guest_name);
      if (payload.guest_email) formData.append('guest_email', payload.guest_email);
      if (payload.images) {
        payload.images.forEach((img) => {
          formData.append('images[]', img);
        });
      }

      const { data } = await apiClient.post<SingleReviewResponse>(`/api/products/${productId}/reviews`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-reviews', productId] });
    },
  });
}
