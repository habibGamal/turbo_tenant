import React, { useState } from 'react';
import { useNotifications } from '../api/useNotifications';
import { useNotificationMutations } from '../api/useNotificationMutations';

export default function DefaultNotificationBell() {
  const [open, setOpen] = useState(false);
  const { data, isLoading } = useNotifications();
  const { markAsRead, markAllAsRead, deleteNotification } = useNotificationMutations();

  const notifications = data?.notifications || [];
  const unreadCount = data?.unread_count || 0;

  return (
    <div className="relative inline-block text-left">
      <button
        onClick={() => setOpen(!open)}
        className="relative p-2 rounded-full hover:bg-muted text-foreground"
        aria-label="Notifications"
      >
        <span className="text-xl">🔔</span>
        {unreadCount > 0 && (
          <span className="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-card border text-card-foreground ring-1 ring-black ring-opacity-5 z-50">
          <div className="p-3 border-b flex justify-between items-center">
            <span className="font-semibold text-sm">Notifications</span>
            {unreadCount > 0 && (
              <button
                onClick={() => markAllAsRead.mutate()}
                className="text-xs text-primary hover:underline"
              >
                Mark all read
              </button>
            )}
          </div>
          <div className="max-h-80 overflow-y-auto divide-y">
            {isLoading ? (
              <div className="p-4 text-center text-sm text-muted-foreground">Loading...</div>
            ) : notifications.length === 0 ? (
              <div className="p-6 text-center text-sm text-muted-foreground">No notifications</div>
            ) : (
              notifications.map((n) => (
                <div
                  key={n.id}
                  className={`p-3 text-sm flex justify-between gap-2 ${!n.read ? 'bg-muted/40 font-medium' : ''}`}
                >
                  <div className="flex-1">
                    <p className="font-semibold">{n.title}</p>
                    <p className="text-xs text-muted-foreground">{n.body}</p>
                  </div>
                  <div className="flex flex-col gap-1 items-end">
                    {!n.read && (
                      <button
                        onClick={() => markAsRead.mutate(n.id)}
                        className="text-[10px] text-primary hover:underline"
                      >
                        Read
                      </button>
                    )}
                    <button
                      onClick={() => deleteNotification.mutate(n.id)}
                      className="text-[10px] text-destructive hover:underline"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
