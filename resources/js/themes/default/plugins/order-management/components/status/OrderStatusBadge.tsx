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
            variant="outline"
            data-testid="order-status-badge"
            className={cn(
                "inline-flex w-fit items-center gap-1.5 rounded-full border-transparent px-3 py-1 font-medium [&_svg]:h-4 [&_svg]:w-4",
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
