import React from "react";
import { Link, router } from "@inertiajs/react";
import ReactPixel from 'react-facebook-pixel';
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Package, ArrowRight, ArrowLeft } from "lucide-react";
import { Cart as CartType } from "@/types";
import { useTranslation } from "react-i18next";

interface CartSummaryProps {
    cart: CartType;
    formatCurrency: (amount: number | undefined) => string;
}

export default function CartSummary({ cart, formatCurrency }: CartSummaryProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const ArrowIcon = isRTL ? ArrowLeft : ArrowRight;

    return (
        <Card className="sticky top-4">
            <CardHeader>
                <CardTitle>{t("orderSummary")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {t("subtotal")}
                        </span>
                        <span className="font-medium">
                            {formatCurrency(cart.total)}
                        </span>
                    </div>
                    <Separator />
                    <div className="flex items-center justify-between">
                        <span className="font-semibold text-lg">
                            {t("total")}
                        </span>
                        <span className="font-bold text-2xl text-primary">
                            {formatCurrency(cart.total)}
                        </span>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="flex-col gap-3">
                <Button
                    className="w-full gap-2"
                    size="lg"
                    onClick={() => {
                        ReactPixel.track("InitiateCheckout", {
                            content_ids: cart.items.map((item) => item.product_id),
                            contents: cart.items.map((item) => ({
                                id: item.product_id,
                                quantity: parseFloat(item.quantity)
                            })),
                            value: cart.total,
                            num_items: cart.items.length,
                            currency: "EGP",
                        });
                        router.visit(route("checkout"));
                    }}
                >
                    {t("proceedToCheckout")}
                    <ArrowIcon className="h-4 w-4" />
                </Button>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Package className="h-4 w-4" />
                        <span>{t("orderEasily")}</span>
                </div>
            </CardFooter>
        </Card>
    );
}
