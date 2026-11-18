import React, { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { LoaderIcon } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useValidateCoupon } from "@/plugins/coupons-promotions/api/useValidateCoupon";
import { AppliedCoupon } from "@/plugins/coupons-promotions/types";
import { localizeErrorMessage } from "@/lib/validationErrors";

interface CouponFormProps {
    cartItems?: any[];
    cartTotal?: number;
    addressId?: number | null;
    guestPhone?: string | null;
    appliedCoupon?: AppliedCoupon | null;
    onCouponApplied?: (coupon: AppliedCoupon) => void;
    onCouponRemoved?: () => void;
}

export default function CouponForm({
    cartItems,
    cartTotal,
    addressId,
    guestPhone,
    appliedCoupon,
    onCouponApplied,
    onCouponRemoved,
}: CouponFormProps) {
    const { t } = useTranslation();
    const [couponCode, setCouponCode] = useState("");
    const [couponError, setCouponError] = useState<string | null>(null);
    const [couponSuccess, setCouponSuccess] = useState<string | null>(null);

    const { mutate, isPending } = useValidateCoupon();

    const handleApplyCoupon = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!couponCode.trim()) return;

        setCouponError(null);
        setCouponSuccess(null);

        mutate(
            {
                code: couponCode.trim(),
                address_id: addressId,
                guest_phone: guestPhone,
            },
            {
                onSuccess: (res) => {
                    if (res?.coupon) {
                        if (onCouponApplied) onCouponApplied(res.coupon);
                        setCouponSuccess(t("couponAppliedSuccessfully", "Coupon applied successfully"));
                    }
                },
                onError: (err: any) => {
                    const rawMessage = err?.response?.data?.message;
                    setCouponError(rawMessage ? localizeErrorMessage(rawMessage) : t("failedToValidateCoupon", "Failed to validate coupon"));
                },
            }
        );
    };

    const handleRemoveCoupon = () => {
        if (onCouponRemoved) onCouponRemoved();
        setCouponCode("");
        setCouponSuccess(null);
        setCouponError(null);
    };

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("couponCode", "Coupon Code")}</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <div className="space-y-4">
                    <form data-testid="coupon-form" onSubmit={handleApplyCoupon} className="flex gap-2">
                        <Input
                            data-testid="coupon-input"
                            placeholder={t("enterCouponCode", "Enter Coupon Code")}
                            value={couponCode}
                            onChange={(e) => setCouponCode(e.target.value)}
                            disabled={!!appliedCoupon || isPending}
                            className="min-w-0 flex-1"
                        />
                        {appliedCoupon ? (
                            <Button
                                data-testid="coupon-remove-button"
                                variant="destructive"
                                className="shrink-0 shadow-none"
                                onClick={handleRemoveCoupon}
                                type="button"
                            >
                                {t("remove", "Remove")}
                            </Button>
                        ) : (
                            <Button
                                data-testid="coupon-apply-button"
                                variant="outline"
                                className="shrink-0 border-border/90 bg-background shadow-none hover:bg-accent/60 dark:bg-card"
                                type="submit"
                                disabled={!couponCode.trim() || isPending}
                            >
                                {isPending ? (
                                    <LoaderIcon className="h-4 w-4 animate-spin" />
                                ) : (
                                    t("apply", "Apply")
                                )}
                            </Button>
                        )}
                    </form>
                    {couponError && (
                        <p data-testid="coupon-error-message" className="text-sm text-destructive">{couponError}</p>
                    )}
                    {couponSuccess && (
                        <p data-testid="coupon-discount-value" className="text-sm text-emerald-600 dark:text-emerald-400 font-medium">{couponSuccess}</p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
