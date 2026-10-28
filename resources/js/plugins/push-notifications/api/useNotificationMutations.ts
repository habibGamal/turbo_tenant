import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';

export function useNotificationMutations() {
  const queryClient = useQueryClient();

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };

  const markAsRead = useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.post(`/notifications/${id}/mark-as-read`);
      return data;
    },
    onSuccess: invalidate,
  });

  const markAllAsRead = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post('/notifications/mark-all-as-read');
      return data;
    },
    onSuccess: invalidate,
  });

  const deleteNotification = useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.delete(`/notifications/${id}`);
      return data;
    },
    onSuccess: invalidate,
  });

  const clearRead = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post('/notifications/clear-read');
      return data;
    },
    onSuccess: invalidate,
  });

  return {
    markAsRead,
    markAllAsRead,
    deleteNotification,
    clearRead,
  };
}
