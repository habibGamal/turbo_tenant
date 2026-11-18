import React from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Package, ArrowRight, ArrowLeft } from "lucide-react";
import { Cart as CartType } from "@/types";
import { useTranslation } from "react-i18next";
import { useCurrency, useFormatMoney } from "@/lib/money";
import { FreeShippingProgress } from "@/themes/default/components/FreeShippingProgress";

interface CartSummaryProps {
    cart: CartType;
    formatCurrency?: (amount: number | string | null | undefined) => string;
}

export default function CartSummary({ cart, formatCurrency }: CartSummaryProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const ArrowIcon = isRTL ? ArrowLeft : ArrowRight;
    const fallbackFormatMoney = useFormatMoney();
    const format = formatCurrency ?? fallbackFormatMoney;
    const currency = useCurrency();

    return (
        <Card variant="recessed" className="sticky top-[calc(var(--header-h,4rem)+1rem)]">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("orderSummary")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <FreeShippingProgress
                    currentAmount={Number((cart as any).subtotal ?? cart.total) || 0}
                />

                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {t("subtotal")}
                        </span>
                        <span data-testid="cart-subtotal" className="font-medium">
                            {format((cart as any).subtotal ?? cart.total)}
                        </span>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        {t("calculatedByGovernorate", t("deliveryFeeCalculatedAtCheckout"))}
                    </p>
                </div>

                <Separator className="bg-border/80" />

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between text-base font-semibold">
                        <span>{t("total")}</span>
                        <span className="text-xl font-bold text-primary">
                            {format(cart.total)}
                        </span>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="flex flex-col gap-3 px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <Link to="/checkout" className="w-full">
                    <Button
                        data-testid="cart-checkout-button"
                        size="lg"
                        className="w-full h-11 md:h-11 gap-2 rounded-xl shadow-none font-semibold"
                        disabled={cart.items.length === 0}
                    >
                        <Package className="h-5 w-5" />
                        {t("proceedToCheckout")}
                        <ArrowIcon className="h-4 w-4" />
                    </Button>
                </Link>
                <p className="text-xs text-center text-muted-foreground">
                    {t("secureCheckoutDescription")}
                </p>
            </CardFooter>
        </Card>
    );
}
