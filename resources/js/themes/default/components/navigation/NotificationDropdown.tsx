import React, { useState, useEffect } from "react";
import axios from "axios";
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

interface Notification {
    id: number;
    title: string;
    body: string;
    read: boolean;
    read_at: string | null;
    created_at: string;
}

export function NotificationDropdown() {
    const { t } = useTranslation();
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        fetchNotifications();

        // Poll for new notifications every 30 seconds
        const interval = setInterval(() => {
            fetchUnreadCount();
        }, 30000);

        return () => clearInterval(interval);
    }, []);

    const fetchNotifications = async () => {
        setLoading(true);
        try {
            const response = await axios.get("/api/notifications");
            setNotifications(response.data.notifications);
            setUnreadCount(response.data.unread_count);
        } catch (error) {
            console.error("Failed to fetch notifications:", error);
        } finally {
            setLoading(false);
        }
    };

    const fetchUnreadCount = async () => {
        try {
            const response = await axios.get("/api/notifications/unread-count");
            setUnreadCount(response.data.count);
        } catch (error) {
            console.error("Failed to fetch unread count:", error);
        }
    };

    const markAsRead = async (id: number) => {
        try {
            await axios.post(`/api/notifications/${id}/mark-as-read`);
            setNotifications((prev) =>
                prev.map((n) =>
                    n.id === id
                        ? {
                              ...n,
                              read: true,
                              read_at: new Date().toISOString(),
                          }
                        : n,
                ),
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));
        } catch (error) {
            console.error("Failed to mark notification as read:", error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.post("/api/notifications/mark-all-as-read");
            setNotifications((prev) =>
                prev.map((n) => ({
                    ...n,
                    read: true,
                    read_at: new Date().toISOString(),
                })),
            );
            setUnreadCount(0);
        } catch (error) {
            console.error("Failed to mark all as read:", error);
        }
    };

    const deleteNotification = async (id: number) => {
        try {
            await axios.delete(`/api/notifications/${id}`);
            setNotifications((prev) => prev.filter((n) => n.id !== id));
            const notification = notifications.find((n) => n.id === id);
            if (notification && !notification.read) {
                setUnreadCount((prev) => Math.max(0, prev - 1));
            }
        } catch (error) {
            console.error("Failed to delete notification:", error);
        }
    };

    const formatNotificationTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return t("justNow");
        if (diffMins < 60) return t("minutesAgo", { count: diffMins });
        if (diffHours < 24) return t("hoursAgo", { count: diffHours });
        if (diffDays < 7) return t("daysAgo", { count: diffDays });
        return date.toLocaleDateString(t("locale"));
    };

    return (
        <DropdownMenu dir="rtl">
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    onClick={fetchNotifications}
                >
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <Badge className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs">
                            {unreadCount > 9 ? "9+" : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 max-h-[500px]">
                <div className="flex items-center justify-between p-3 border-b">
                    <h3 className="font-semibold">{t("notifications")}</h3>
                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={markAllAsRead}
                        >
                            <Check className="h-3 w-3 ltr:mr-1 rtl:ml-1" />
                            {t("markAllRead")}
                        </Button>
                    )}
                </div>
                <ScrollArea className="max-h-[400px]" dir="rtl">
                    {loading ? (
                        <div className="p-4 text-center text-sm text-muted-foreground">
                            {t("loading")}...
                        </div>
                    ) : notifications.length === 0 ? (
                        <div className="p-8 text-center">
                            <Bell className="h-12 w-12 mx-auto text-muted-foreground mb-2 opacity-50" />
                            <p className="text-sm text-muted-foreground">
                                {t("noNotifications")}
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
                                                            markAsRead(
                                                                notification.id,
                                                            )
                                                        }
                                                    >
                                                        <Check className="h-3 w-3 ltr:mr-1 rtl:ml-1" />
                                                        {t("markRead")}
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-5 px-2 text-[10px] text-destructive"
                                                    onClick={() =>
                                                        deleteNotification(
                                                            notification.id,
                                                        )
                                                    }
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
