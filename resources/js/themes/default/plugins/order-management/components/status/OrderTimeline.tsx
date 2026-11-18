import React from "react";
import { CheckCircle, Clock, XCircle } from "lucide-react";
import { useTranslation } from "react-i18next";
import { cn } from "@/lib/utils";

export interface OrderTimelineProps {
    status: string;
    orderType?: string;
    className?: string;
}

export function OrderTimeline({
    status,
    orderType = "web_delivery",
    className,
}: OrderTimelineProps) {
    const { t } = useTranslation();

    if (status === "cancelled") {
        return (
            <div
                data-testid="order-status-timeline"
                className={cn(
                    "flex items-center justify-center py-6 text-destructive gap-4",
                    className
                )}
            >
                <XCircle className="w-10 h-10 shrink-0" />
                <div>
                    <div className="text-base font-semibold">
                        {t("orderCancelled")}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {t("orderWasCancelledMessage")}
                    </div>
                </div>
            </div>
        );
    }

    const orderTimeline = [
        { status: "pending", label: t("orderPlaced") },
        { status: "processing", label: t("preparing") },
        {
            status: "out_for_delivery",
            label:
                orderType === "web_delivery"
                    ? t("outForDelivery")
                    : t("readyForPickup"),
        },
        {
            status: "completed",
            label:
                orderType === "web_delivery"
                    ? t("delivered")
                    : t("completed"),
        },
    ];

    const getStatusIndex = (currentStatus: string): number => {
        if (currentStatus === "delivered") return 3;
        const index = orderTimeline.findIndex(
            (item) => item.status === currentStatus
        );
        return index >= 0 ? index : 0;
    };

    const currentStatusIndex = getStatusIndex(status);
    const progressPercent =
        (currentStatusIndex / (orderTimeline.length - 1)) * 100;

    return (
        <div
            className={cn("relative py-2", className)}
            data-testid="order-status-timeline"
        >
            <div className="flex justify-between mb-2">
                {orderTimeline.map((item, index) => {
                    const isCompleted = index <= currentStatusIndex;
                    const isCurrent = index === currentStatusIndex;

                    return (
                        <div
                            key={item.status}
                            className="flex flex-col items-center flex-1"
                        >
                            <div
                                className={cn(
                                    "w-10 h-10 rounded-full flex items-center justify-center mb-2 transition-colors",
                                    isCompleted
                                        ? "bg-primary text-primary-foreground"
                                        : "border border-border/90 bg-background text-muted-foreground dark:bg-card",
                                    isCurrent ? "ring-4 ring-primary/20" : ""
                                )}
                            >
                                {isCompleted ? (
                                    <CheckCircle className="w-5 h-5" />
                                ) : (
                                    <Clock className="w-5 h-5" />
                                )}
                            </div>
                            <div className="text-xs text-center font-medium px-1">
                                {item.label}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Connecting Progress Track */}
            <div className="absolute top-7 inset-x-0 h-0.5 bg-border -z-10">
                <div
                    className="h-full bg-primary transition-all ms-0 me-auto"
                    style={{ width: `${progressPercent}%` }}
                />
            </div>
        </div>
    );
}

export default OrderTimeline;
