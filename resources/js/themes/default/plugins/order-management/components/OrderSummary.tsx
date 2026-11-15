import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Badge } from "@/components/ui/badge";
import { ImageWithFallback } from "@/components/ui/image";
import { LoaderIcon } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Cart } from "@/types";
import { useFormatMoney } from "@/lib/money";

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

    const formatMoney = useFormatMoney();

    const getText = (text: string, textAr?: string | null) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    return (
        <Card className="sticky top-[calc(var(--header-h,4rem)+1rem)]" data-testid="order-summary">
            <CardHeader>
                <CardTitle>{t("orderSummary")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Cart Items List */}
                <div className="space-y-2 max-h-96 overflow-y-auto">
                    {cart.items.map((item: any) => {
                        const isPackage = item.type === "package";

                        return (
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
                                            <div className="font-medium text-xs leading-tight flex items-center gap-1.5 flex-wrap">
                                                <span className="truncate">{getText(item.product?.name || '', item.product?.name_ar)}</span>
                                                {isPackage && (
                                                    <Badge variant="secondary" className="text-[9px] py-0 px-1 shrink-0">
                                                        {t("combo", "Combo")}
                                                    </Badge>
                                                )}
                                            </div>
                                            {!isPackage && item.variant && (
                                                <div className="text-[10px] text-muted-foreground truncate">
                                                    {getText(item.variant.name, item.variant.name_ar)}
                                                </div>
                                            )}
                                        </div>
                                        <div className="text-xs font-semibold text-primary shrink-0">
                                            {formatMoney(item.price)}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 mt-0.5">
                                        {!isPackage && item.product?.sell_by_weight &&
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

                                    {/* Components for Package lines */}
                                    {isPackage && item.components && item.components.length > 0 && (
                                        <div className="text-[10px] text-muted-foreground mt-1 space-y-0.5 ps-1.5 border-s border-border">
                                            {item.components.map((c: any, idx: number) => {
                                                const groupName = getText(c.group_name, c.group_name_ar);
                                                const prodName = getText(c.product_name, c.product_name_ar);
                                                const varName = c.variant_name ? getText(c.variant_name, c.variant_name_ar) : null;
                                                const adj = Number(c.unit_price_adjustment) || 0;
                                                const qty = Number(c.quantity) || 1;

                                                return (
                                                    <div key={idx} className="space-y-0.5">
                                                        <div className="truncate">
                                                            <span className="font-medium">{groupName}:</span> {qty}× {prodName}
                                                            {varName ? ` (${varName})` : ''}
                                                            {adj > 0 ? ` +${formatMoney(adj)}` : ''}
                                                        </div>
                                                        {c.extras && c.extras.length > 0 && (
                                                            <div className="ps-2 space-y-0.5 text-[9px] text-muted-foreground">
                                                                {c.extras.map((extra: any, extraIdx: number) => {
                                                                    const extraName = getText(extra.name || extra.extra_name, extra.name_ar || extra.extra_name_ar);
                                                                    const extraPrice = Number(extra.price || extra.extra_price) || 0;
                                                                    const extraQty = Number(extra.quantity) || 1;
                                                                    return (
                                                                        <div key={extraIdx} className="truncate">
                                                                            + {extraName}
                                                                            {extraQty > 1 && <span className="ms-1">×{extraQty}</span>}
                                                                            {extraPrice > 0 && <span className="ms-1">+{formatMoney(extraPrice * extraQty)}</span>}
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        )}
                                                        {c.notes && (
                                                            <div className="ps-2 text-[9px] text-muted-foreground italic truncate">
                                                                "{c.notes}"
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {isPackage && item.notes && (
                                        <div className="text-[10px] text-muted-foreground italic mt-0.5 truncate">
                                            "{item.notes}"
                                        </div>
                                    )}

                                    {!isPackage && item.extras && item.extras.length > 0 && (
                                        <div className="text-[10px] text-muted-foreground mt-0.5 truncate">
                                            {item.extras.map((extra: any) => getText(extra.name, extra.name_ar)).join(", ")}
                                        </div>
                                    )}
                                    <div className="text-sm font-semibold text-primary mt-1">
                                        {formatMoney(item.subtotal)}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <Separator />

                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {t("subtotal")}
                        </span>
                        <span className="font-medium">
                            {formatMoney(cart?.total)}
                        </span>
                    </div>

                    {appliedCoupon && (
                        <div className="flex items-center justify-between text-sm text-emerald-600 dark:text-emerald-400">
                            <span>
                                {t("discount")} ({appliedCoupon.code})
                            </span>
                            <span className="font-medium">
                                - {formatMoney(appliedCoupon.discount)}
                            </span>
                        </div>
                    )}

                    {tax > 0 && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">{t("tax")}</span>
                            <span className="font-medium">{formatMoney(tax)}</span>
                        </div>
                    )}

                    {service > 0 && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {t("codFees")}
                            </span>
                            <span className="font-medium">
                                {formatMoney(service)}
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
                                        ? formatMoney(deliveryFee)
                                        : t("free")}
                            </span>
                        </div>
                    )}

                    <Separator />

                    <div className="flex items-center justify-between">
                        <span className="font-semibold text-lg">{t("total")}</span>
                        <span className="font-bold text-2xl text-primary">
                            {formatMoney(totalAmount)}
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
                            <LoaderIcon className="me-2 h-4 w-4 animate-spin" />
                            {t("placingOrder")}
                        </>
                    ) : (
                        t("placeOrder")
                    )}
                </Button>
            </CardContent>

            {/* Mobile Sticky Bottom Bar: below md it sits above MobileBottomNav (4.5rem), the spacer keeps the page end visible */}
            <div className="lg:hidden h-24" aria-hidden="true" />
            <div className="lg:hidden fixed bottom-[calc(4.5rem+env(safe-area-inset-bottom))] md:bottom-0 inset-x-0 z-40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 border-t border-border p-4 shadow-lg md:pb-[calc(1rem+env(safe-area-inset-bottom))]">
                <div className="flex items-center justify-between gap-4 container mx-auto">
                    <div className="flex flex-col">
                        <span className="text-xs text-muted-foreground">{t("total")}</span>
                        <span className="text-lg font-bold text-primary">
                            {formatMoney(totalAmount)}
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
                                <LoaderIcon className="me-2 h-4 w-4 animate-spin" />
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
