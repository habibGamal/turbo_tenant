import { ApiSuccess, Paginated } from '@/core/api/types';

export interface NotificationItem {
  id: number;
  title: string;
  body: string;
  data?: any;
  read: boolean;
  read_at: string | null;
  type?: string;
  created_at: string;
}

export type NotificationsResponse = Paginated<NotificationItem>;
export type SingleNotificationResponse = ApiSuccess<NotificationItem>;
export type UnreadCountResponse = ApiSuccess<{ count: number }>;
