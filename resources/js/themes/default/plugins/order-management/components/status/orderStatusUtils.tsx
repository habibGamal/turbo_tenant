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
            return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400";
        case "processing":
            return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
        case "out_for_delivery":
            return "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400";
        case "delivered":
        case "completed":
            return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400";
        case "cancelled":
            return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400";
        default:
            return "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400";
    }
}

export function getPaymentStatusColor(status: string): string {
    switch (status) {
        case "pending":
            return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400";
        case "processing":
            return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400";
        case "completed":
            return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400";
        case "failed":
            return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400";
        default:
            return "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400";
    }
}
