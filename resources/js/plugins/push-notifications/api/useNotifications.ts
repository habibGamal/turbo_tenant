import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { NotificationItem, NotificationsResponse, UnreadCountResponse } from '../types';

export function useNotifications() {
  return useQuery<NotificationItem[]>({
    queryKey: ['notifications'],
    queryFn: async () => {
      const { data } = await apiClient.get<NotificationsResponse>('/api/notifications');
      return data.data;
    },
    staleTime: 1000 * 15,
    refetchInterval: 30000, // Background polling every 30s
  });
}

export function useUnreadNotificationsCount() {
  return useQuery<{ count: number }>({
    queryKey: ['notifications', 'unread-count'],
    queryFn: async () => {
      const { data } = await apiClient.get<UnreadCountResponse>('/api/notifications/unread-count');
      return data.data;
    },
    staleTime: 1000 * 15,
    refetchInterval: 30000,
  });
}
