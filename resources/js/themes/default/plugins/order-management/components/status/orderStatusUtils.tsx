import React from "react";
import {
    Clock,
    Package,
    Truck,
    CheckCircle,
    XCircle,
} from "lucide-react";

export const TERMINAL_STATUSES = ["completed", "delivered", "cancelled"] as const;

export function getStatusIcon(status: string): React.ReactNode {
    switch (status) {
        case "pending":
            return <Clock className="w-5 h-5" />;
        case "processing":
            return <Package className="w-5 h-5" />;
        case "out_for_delivery":
            return <Truck className="w-5 h-5" />;
        case "delivered":
        case "completed":
            return <CheckCircle className="w-5 h-5" />;
        case "cancelled":
            return <XCircle className="w-5 h-5" />;
        default:
            return <Clock className="w-5 h-5" />;
    }
}

export function getStatusColor(status: string): string {
    switch (status) {
        case "pending":
            return "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400";
        case "processing":
            return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
        case "out_for_delivery":
            return "bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400";
        case "delivered":
        case "completed":
            return "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400";
        case "cancelled":
            return "bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive";
        default:
            return "bg-muted text-muted-foreground";
    }
}

export function getPaymentStatusColor(status: string): string {
    switch (status) {
        case "pending":
            return "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400";
        case "processing":
            return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
        case "completed":
            return "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400";
        case "failed":
            return "bg-destructive/10 text-destructive dark:bg-destructive/20 dark:text-destructive";
        default:
            return "bg-muted text-muted-foreground";
    }
}
