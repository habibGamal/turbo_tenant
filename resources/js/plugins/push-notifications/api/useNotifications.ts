import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { NotificationsResponse } from '../types';

export function useNotifications() {
  return useQuery<NotificationsResponse>({
    queryKey: ['notifications'],
    queryFn: async () => {
      const { data } = await apiClient.get<NotificationsResponse>('/notifications');
      return data;
    },
    staleTime: 1000 * 15,
    refetchInterval: 30000, // Background polling every 30s
  });
}

export function useUnreadNotificationsCount() {
  return useQuery<{ count: number }>({
    queryKey: ['notifications', 'unread-count'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ count: number }>('/notifications/unread-count');
      return data;
    },
    staleTime: 1000 * 15,
    refetchInterval: 30000,
  });
}
