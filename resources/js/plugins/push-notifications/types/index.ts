export interface NotificationItem {
  id: number;
  title: string;
  body: string;
  read: boolean;
  read_at: string | null;
  created_at: string;
}

export interface NotificationsResponse {
  notifications: NotificationItem[];
  unread_count: number;
}
