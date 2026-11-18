import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { LoaderIcon } from "lucide-react";
import { useTranslation } from "react-i18next";
import { apiClient } from "@/core/api/client";
import { ApiSuccess } from "@/core/api/types";
import { CartItem } from "@/types";
import { localizeErrorMessage } from "@/lib/validationErrors";

interface AppliedCoupon {
    id: number;
    code: string;
    discount: number;
    type: string;
    value: number;
    free_shipping: boolean;
}

interface CouponCodeSectionProps {
    cartItems?: CartItem[];
    cartTotal?: number;
    addressId: number | null;
    guestPhone?: string | null;
    appliedCoupon: AppliedCoupon | null;
    onCouponApplied: (coupon: AppliedCoupon) => void;
    onCouponRemoved: () => void;
}

export default function CouponCodeSection({
    cartItems,
    cartTotal,
    addressId,
    guestPhone,
    appliedCoupon,
    onCouponApplied,
    onCouponRemoved,
}: CouponCodeSectionProps) {
    const { t } = useTranslation();
    const [couponCode, setCouponCode] = useState("");
    const [couponError, setCouponError] = useState<string | null>(null);
    const [couponSuccess, setCouponSuccess] = useState<string | null>(null);
    const [isValidatingCoupon, setIsValidatingCoupon] = useState(false);

    const handleApplyCoupon = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!couponCode.trim()) return;

        setIsValidatingCoupon(true);
        setCouponError(null);
        setCouponSuccess(null);

        try {
            const response = await apiClient.post<ApiSuccess<{ coupon: AppliedCoupon }>>("/api/coupons/validate", {
                code: couponCode.trim(),
                address_id: addressId,
                guest_phone: guestPhone || undefined,
            });

            const data = response.data.data;
            if (data?.coupon) {
                onCouponApplied(data.coupon);
                setCouponSuccess(t("couponAppliedSuccessfully", "Coupon applied successfully"));
            }
        } catch (error: any) {
            const rawMessage = error.response?.data?.message;
            if (rawMessage) {
                setCouponError(localizeErrorMessage(rawMessage));
            } else {
                setCouponError(t("failedToValidateCoupon"));
            }
        } finally {
            setIsValidatingCoupon(false);
        }
    };

    const handleRemoveCoupon = () => {
        onCouponRemoved();
        setCouponCode("");
        setCouponSuccess(null);
        setCouponError(null);
    };

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("couponCode")}</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <div className="space-y-4">
                    <form data-testid="coupon-form" onSubmit={handleApplyCoupon} className="flex gap-2">
                        <Input
                            data-testid="coupon-input"
                            placeholder={t("enterCouponCode")}
                            value={couponCode}
                            onChange={(e) => setCouponCode(e.target.value)}
                            disabled={!!appliedCoupon || isValidatingCoupon}
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
                                {t("remove")}
                            </Button>
                        ) : (
                            <Button
                                data-testid="coupon-apply-button"
                                variant="outline"
                                className="shrink-0 border-border/90 bg-background shadow-none hover:bg-accent/60 dark:bg-card"
                                disabled={!couponCode.trim() || isValidatingCoupon}
                                type="submit"
                            >
                                {isValidatingCoupon ? (
                                    <LoaderIcon className="h-4 w-4 animate-spin" />
                                ) : (
                                    t("apply")
                                )}
                            </Button>
                        )}
                    </form>
                    {couponError && (
                        <p data-testid="coupon-error-message" className="text-sm text-destructive">{couponError}</p>
                    )}
                    {couponSuccess && (
                        <p data-testid="coupon-discount-value" className="text-sm text-emerald-600 dark:text-emerald-400">{couponSuccess}</p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
