import React from "react";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Bell, Check, Trash2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useNotifications } from "@/plugins/push-notifications/api/useNotifications";
import { useNotificationMutations } from "@/plugins/push-notifications/api/useNotificationMutations";

export function NotificationDropdown() {
    const { t, i18n } = useTranslation();
    const { data, isLoading } = useNotifications();
    const { markAsRead, markAllAsRead, deleteNotification } = useNotificationMutations();

    const notifications = data?.notifications || [];
    const unreadCount = data?.unread_count || 0;

    const formatNotificationTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return t("justNow", "Just now");
        if (diffMins < 60) return t("minutesAgo", "{{count}}m ago", { count: diffMins });
        if (diffHours < 24) return t("hoursAgo", "{{count}}h ago", { count: diffHours });
        if (diffDays < 7) return t("daysAgo", "{{count}}d ago", { count: diffDays });
        return date.toLocaleDateString();
    };

    return (
        <DropdownMenu dir={i18n.dir()}>
            <DropdownMenuTrigger asChild>
                <Button
                    data-testid="notifications-trigger"
                    variant="ghost"
                    size="icon"
                    aria-label={t("notifications", "Notifications")}
                    className="relative"
                >
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <Badge data-testid="notifications-badge-count" className="absolute -top-1 -end-1 h-5 w-5 flex items-center justify-center p-0 text-xs">
                            {unreadCount > 9 ? "9+" : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent data-testid="notifications-dropdown" align="end" className="w-80 max-h-[500px]">
                <div className="flex items-center justify-between p-3 border-b">
                    <h3 className="font-semibold">{t("notifications", "Notifications")}</h3>
                    {unreadCount > 0 && (
                        <Button
                            data-testid="mark-all-read-button"
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={() => markAllAsRead.mutate()}
                            disabled={markAllAsRead.isPending}
                        >
                            <Check className="h-3 w-3 me-1" />
                            {t("markAllRead", "Mark all as read")}
                        </Button>
                    )}
                </div>
                <ScrollArea className="max-h-[400px]" dir={i18n.dir()}>
                    {isLoading ? (
                        <div className="p-4 text-center text-sm text-muted-foreground">
                            {t("loading", "Loading...")}
                        </div>
                    ) : notifications.length === 0 ? (
                        <div className="p-8 text-center">
                            <Bell className="h-12 w-12 mx-auto text-muted-foreground mb-2 opacity-50" />
                            <p className="text-sm text-muted-foreground">
                                {t("noNotifications", "No notifications")}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y">
                            {notifications.map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`p-3 hover:bg-accent transition-colors ${
                                        !notification.read ? "bg-accent/50" : ""
                                    }`}
                                >
                                    <div className="flex items-start gap-2">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="font-medium text-sm truncate">
                                                    {notification.title}
                                                </p>
                                                {!notification.read && (
                                                    <div className="h-2 w-2 rounded-full bg-primary shrink-0 mt-1.5" />
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">
                                                {notification.body}
                                            </p>
                                            <div className="flex items-center gap-2 mt-2">
                                                <span className="text-[10px] text-muted-foreground">
                                                    {formatNotificationTime(
                                                        notification.created_at,
                                                    )}
                                                </span>
                                                {!notification.read && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-5 px-2 text-[10px]"
                                                        onClick={() =>
                                                            markAsRead.mutate(
                                                                notification.id,
                                                            )
                                                        }
                                                        disabled={markAsRead.isPending}
                                                    >
                                                        <Check className="h-3 w-3 me-1" />
                                                        {t("markRead", "Mark read")}
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label={t("delete", "Delete")}
                                                    className="h-5 px-2 text-[10px] text-destructive"
                                                    onClick={() =>
                                                        deleteNotification.mutate(
                                                            notification.id,
                                                        )
                                                    }
                                                    disabled={deleteNotification.isPending}
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </ScrollArea>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default NotificationDropdown;
