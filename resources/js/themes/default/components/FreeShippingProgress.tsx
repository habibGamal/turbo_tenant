import { CheckCircle2, Truck } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Progress } from "@/components/ui/progress";
import { useTenantSafe } from "@/core/context/TenantContext";
import { useFormatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";

interface FreeShippingProgressProps {
    currentAmount: number;
    threshold?: number;
    className?: string;
}

/**
 * Displays progress toward the store's automatic free-shipping threshold.
 */
export function FreeShippingProgress({
    currentAmount,
    threshold,
    className,
}: FreeShippingProgressProps) {
    const { t } = useTranslation();
    const tenant = useTenantSafe();
    const formatMoney = useFormatMoney();

    const resolvedThreshold = threshold ?? Number(tenant?.settings?.free_shipping_threshold || 0);
    if (!Number.isFinite(resolvedThreshold) || resolvedThreshold <= 0) {
        return null;
    }

    const remaining = Math.max(0, resolvedThreshold - currentAmount);
    const unlocked = remaining <= 0;
    const progressPercent = Math.min(100, Math.max(0, (currentAmount / resolvedThreshold) * 100));

    if (unlocked) {
        return (
            <div
                data-testid="free-shipping-unlocked"
                className={cn(
                    "flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-700 dark:text-emerald-300",
                    className
                )}
            >
                <CheckCircle2 className="h-4 w-4 shrink-0" />
                <span>{t("freeShippingUnlocked", "You unlocked free shipping!")}</span>
            </div>
        );
    }

    return (
        <div
            data-testid="free-shipping-progress"
            className={cn(
                "space-y-2 rounded-lg border border-border/60 bg-muted/40 px-3 py-2.5",
                className
            )}
        >
            <div className="flex items-center gap-2 text-xs font-medium text-foreground">
                <Truck className="h-4 w-4 shrink-0 text-primary" />
                <span>
                    {t("addMoreForFreeShipping", {
                        amount: formatMoney(remaining),
                    })}
                </span>
            </div>
            <Progress value={progressPercent} className="h-1.5" dir="ltr" />
        </div>
    );
}

export default FreeShippingProgress;
