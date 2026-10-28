import React from "react";
import { Badge } from "@/components/ui/badge";
import { useTranslation } from "react-i18next";
import { cn } from "@/lib/utils";
import { getStatusColor, getStatusIcon } from "./orderStatusUtils";

export interface OrderStatusBadgeProps {
    status: string;
    className?: string;
    showIcon?: boolean;
}

export function OrderStatusBadge({
    status,
    className,
    showIcon = true,
}: OrderStatusBadgeProps) {
    const { t } = useTranslation();

    return (
        <Badge
            data-testid="order-status-badge"
            className={cn(
                "flex items-center gap-1.5 font-medium transition-colors",
                getStatusColor(status),
                className
            )}
        >
            {showIcon && getStatusIcon(status)}
            <span>{t(status)}</span>
        </Badge>
    );
}

export default OrderStatusBadge;
