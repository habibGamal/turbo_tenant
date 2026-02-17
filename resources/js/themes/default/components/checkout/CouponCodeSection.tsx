import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Loader2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import axios from "axios";
import { CartItem } from "@/types";

interface AppliedCoupon {
    id: number;
    code: string;
    discount: number;
    type: string;
    value: number;
    free_shipping: boolean;
}

interface CouponCodeSectionProps {
    cartItems: CartItem[];
    cartTotal: number;
    addressId: number | null;
    appliedCoupon: AppliedCoupon | null;
    onCouponApplied: (coupon: AppliedCoupon) => void;
    onCouponRemoved: () => void;
}

export default function CouponCodeSection({
    cartItems,
    cartTotal,
    addressId,
    appliedCoupon,
    onCouponApplied,
    onCouponRemoved,
}: CouponCodeSectionProps) {
    const { t } = useTranslation();
    const [couponCode, setCouponCode] = useState("");
    const [couponError, setCouponError] = useState<string | null>(null);
    const [couponSuccess, setCouponSuccess] = useState<string | null>(null);
    const [isValidatingCoupon, setIsValidatingCoupon] = useState(false);

    const handleApplyCoupon = async () => {
        if (!couponCode.trim()) return;

        setIsValidatingCoupon(true);
        setCouponError(null);
        setCouponSuccess(null);

        try {
            const response = await axios.post(route("coupons.validate"), {
                code: couponCode,
                cart_items: cartItems,
                sub_total: cartTotal,
                address_id: addressId,
            });

            if (response.data.valid) {
                onCouponApplied(response.data.coupon);
                setCouponSuccess(response.data.message);
            } else {
                setCouponError(response.data.message);
            }
        } catch (error: any) {
            if (error.response?.data?.message) {
                setCouponError(error.response.data.message);
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
        <Card>
            <CardHeader>
                <CardTitle>{t("couponCode")}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    <div className="flex gap-2">
                        <Input
                            placeholder={t("enterCouponCode")}
                            value={couponCode}
                            onChange={(e) => setCouponCode(e.target.value)}
                            disabled={!!appliedCoupon || isValidatingCoupon}
                        />
                        {appliedCoupon ? (
                            <Button
                                variant="destructive"
                                onClick={handleRemoveCoupon}
                                type="button"
                            >
                                {t("remove")}
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={handleApplyCoupon}
                                disabled={!couponCode || isValidatingCoupon}
                                type="button"
                            >
                                {isValidatingCoupon ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    t("apply")
                                )}
                            </Button>
                        )}
                    </div>
                    {couponError && (
                        <p className="text-sm text-destructive">{couponError}</p>
                    )}
                    {couponSuccess && (
                        <p className="text-sm text-green-600">{couponSuccess}</p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
