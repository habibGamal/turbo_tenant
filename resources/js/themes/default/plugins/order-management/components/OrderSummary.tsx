import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { ImageWithFallback } from "@/components/ui/image";
import { Loader2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Cart } from "@/types";

interface AppliedCoupon {
    id: number;
    code: string;
    discount: number;
    type: string;
    value: number;
    free_shipping: boolean;
}

interface OrderSummaryProps {
    cart: Cart;
    deliveryFee: number;
    tax: number;
    service: number;
    discount: number;
    totalAmount: number;
    appliedCoupon: AppliedCoupon | null;
    isSubmitting: boolean;
    onPlaceOrder: () => void;
    orderType?: string;
    hasAddressSelected?: boolean;
}

export default function OrderSummary({
    cart,
    deliveryFee,
    tax,
    service,
    discount,
    totalAmount,
    appliedCoupon,
    isSubmitting,
    onPlaceOrder,
    orderType,
    hasAddressSelected = true,
}: OrderSummaryProps) {
    const { t, i18n } = useTranslation();

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const formatCurrency = (amount: number | undefined) => {
        const safeAmount = amount ?? 0;
        return `${safeAmount.toFixed(2)} ${t("currency")}`;
    };

    return (
        <Card className="sticky top-4" data-testid="order-summary">
            <CardHeader>
                <CardTitle>{t("orderSummary")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Cart Items List */}
                <div className="space-y-2 max-h-96 overflow-y-auto">
                    {cart.items.map((item) => (
                        <div
                            key={item.id}
                            className="flex gap-2 pb-2 border-b border-border/50 last:border-0"
                        >
                            {/* Item Image */}
                            <div className="w-12 h-12 rounded-md bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden shrink-0">
                                <ImageWithFallback
                                    src={item.product?.image}
                                    alt={getText(item.product?.name || '', item.product?.name_ar) || t("product")}
                                    className="w-full h-full object-cover"
                                />
                            </div>

                            {/* Item Details */}
                            <div className="flex-1 min-w-0">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex-1 min-w-0">
                                        <div className="font-medium text-xs leading-tight truncate">
                                            {getText(item.product?.name || '', item.product?.name_ar)}
                                        </div>
                                        {item.variant && (
                                            <div className="text-[10px] text-muted-foreground truncate">
                                                {getText(item.variant.name, item.variant.name_ar)}
                                            </div>
                                        )}
                                    </div>
                                    <div className="text-xs font-semibold text-primary shrink-0">
                                        {formatCurrency(item.price)}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 mt-0.5">
                                    {item.product?.sell_by_weight &&
                                    item.weight_option_value ? (
                                        <span className="text-[10px] text-muted-foreground">
                                            {item.weight_option_value.value}{" "}
                                            {item.product?.weight_option?.unit || "kg"}
                                        </span>
                                    ) : (
                                        <span className="text-[10px] text-muted-foreground">
                                            {t("qty")}: {item.quantity}
                                        </span>
                                    )}
                                </div>
                                {item.extras.length > 0 && (
                                    <div className="text-[10px] text-muted-foreground mt-0.5 truncate">
                                        {item.extras.map((extra) => getText(extra.name, extra.name_ar)).join(", ")}
                                    </div>
                                )}
                                <div className="text-sm font-semibold text-primary mt-1">
                                    {formatCurrency(item.subtotal)}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <Separator />

                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {t("subtotal")}
                        </span>
                        <span className="font-medium">
                            {formatCurrency(cart?.total)}
                        </span>
                    </div>

                    {appliedCoupon && (
                        <div className="flex items-center justify-between text-sm text-green-600">
                            <span>
                                {t("discount")} ({appliedCoupon.code})
                            </span>
                            <span className="font-medium">
                                - {formatCurrency(appliedCoupon.discount)}
                            </span>
                        </div>
                    )}

                    {tax > 0 && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">{t("tax")}</span>
                            <span className="font-medium">{formatCurrency(tax)}</span>
                        </div>
                    )}

                    {service > 0 && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {t("codFees")}
                            </span>
                            <span className="font-medium">
                                {formatCurrency(service)}
                            </span>
                        </div>
                    )}

                    {orderType !== "web_takeaway" && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {t("deliveryFee")}
                            </span>
                            <span className={hasAddressSelected ? "font-medium" : "text-xs text-muted-foreground italic"}>
                                {!hasAddressSelected
                                    ? t("selectAddress", t("selectAddressToSeeFee", "اختر العنوان"))
                                    : deliveryFee > 0
                                        ? formatCurrency(deliveryFee)
                                        : t("free")}
                            </span>
                        </div>
                    )}

                    <Separator />

                    <div className="flex items-center justify-between">
                        <span className="font-semibold text-lg">{t("total")}</span>
                        <span className="font-bold text-2xl text-primary">
                            {formatCurrency(totalAmount)}
                        </span>
                    </div>
                </div>

                <Button
                    data-testid="place-order-button"
                    className="w-full"
                    size="lg"
                    onClick={onPlaceOrder}
                    disabled={isSubmitting}
                >
                    {isSubmitting ? (
                        <>
                            <Loader2 className="ltr:mr-2 rtl:ml-2 h-4 w-4 animate-spin" />
                            {t("placingOrder")}
                        </>
                    ) : (
                        t("placeOrder")
                    )}
                </Button>
            </CardContent>

            {/* Mobile Sticky Bottom Bar: below md it sits above MobileBottomNav (4.5rem), the spacer keeps the page end visible */}
            <div className="lg:hidden h-24" aria-hidden="true" />
            <div className="lg:hidden fixed bottom-[calc(4.5rem+env(safe-area-inset-bottom))] md:bottom-0 left-0 right-0 z-40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 border-t border-border p-4 shadow-lg md:pb-[calc(1rem+env(safe-area-inset-bottom))]">
                <div className="flex items-center justify-between gap-4 container mx-auto">
                    <div className="flex flex-col">
                        <span className="text-xs text-muted-foreground">{t("total")}</span>
                        <span className="text-lg font-bold text-primary">
                            {formatCurrency(totalAmount)}
                        </span>
                    </div>
                    <Button
                        data-testid="mobile-place-order-button"
                        size="lg"
                        className="flex-1 max-w-[200px]"
                        onClick={onPlaceOrder}
                        disabled={isSubmitting}
                    >
                        {isSubmitting ? (
                            <>
                                <Loader2 className="ltr:mr-2 rtl:ml-2 h-4 w-4 animate-spin" />
                                {t("placingOrder")}
                            </>
                        ) : (
                            t("placeOrder")
                        )}
                    </Button>
                </div>
            </div>
        </Card>
    );
}
