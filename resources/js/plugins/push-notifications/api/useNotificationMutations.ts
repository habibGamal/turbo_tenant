import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { NotificationItem, SingleNotificationResponse } from '../types';

export function useNotificationMutations() {
  const queryClient = useQueryClient();

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };

  const markAsRead = useMutation({
    mutationFn: async (id: number): Promise<NotificationItem> => {
      const { data } = await apiClient.post<SingleNotificationResponse>(`/api/notifications/${id}/mark-as-read`);
      return data.data;
    },
    onSuccess: invalidate,
  });

  const markAllAsRead = useMutation({
    mutationFn: async (): Promise<null> => {
      const { data } = await apiClient.post<ApiSuccess<null>>('/api/notifications/mark-all-as-read');
      return data.data;
    },
    onSuccess: invalidate,
  });

  const deleteNotification = useMutation({
    mutationFn: async (id: number): Promise<null> => {
      const { data } = await apiClient.delete<ApiSuccess<null>>(`/api/notifications/${id}`);
      return data.data;
    },
    onSuccess: invalidate,
  });

  const clearRead = useMutation({
    mutationFn: async (): Promise<null> => {
      const { data } = await apiClient.post<ApiSuccess<null>>('/api/notifications/clear-read');
      return data.data;
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
