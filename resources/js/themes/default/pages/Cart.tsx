import React, { useState } from "react";
import { Head, Link } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    ArrowRight,
    ArrowLeft,
    Trash2,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Cart as CartType, PageProps } from "@/types";
import {
    updateCartItem,
    removeCartItem,
    clearCart as clearCartUtil,
} from "@/utils/cartUtils";
import CartItem from "@/themes/default/components/cart/CartItem";
import CartSummary from "@/themes/default/components/cart/CartSummary";
import EmptyCart from "@/themes/default/components/cart/EmptyCart";

interface CartPageProps extends PageProps {
    cart: CartType;
}

export default function Cart({ cart: initialCart, auth }: CartPageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const [cart, setCart] = useState<CartType>(initialCart);
    const [updatingItems, setUpdatingItems] = useState<Set<number | string>>(
        new Set()
    );

    const BackArrowIcon = isRTL ? ArrowRight : ArrowLeft;

    const formatCurrency = (amount: number | undefined) => {
        const safeAmount = amount ?? 0;
        return `${safeAmount.toFixed(2)} ${t("currency")}`;
    };

    const updateQuantity = async (
        itemId: number | string,
        newQuantity: string
    ) => {
        if (parseFloat(newQuantity) < 0.001) return;

        setUpdatingItems((prev) => new Set(prev).add(itemId));

        await updateCartItem(
            itemId,
            { quantity: newQuantity },
            {
                onSuccess: (data) => {
                    if (data.cart) {
                        setCart(data.cart);
                    }
                },
                onFinally: () => {
                    setUpdatingItems((prev) => {
                        const newSet = new Set(prev);
                        newSet.delete(itemId);
                        return newSet;
                    });
                },
            }
        );
    };

    const removeItem = async (itemId: number | string) => {
        setUpdatingItems((prev) => new Set(prev).add(itemId));

        await removeCartItem(itemId, {
            onSuccess: (data) => {
                if (data.cart) {
                    setCart(data.cart);
                }
            },
            onFinally: () => {
                setUpdatingItems((prev) => {
                    const newSet = new Set(prev);
                    newSet.delete(itemId);
                    return newSet;
                });
            },
        });
    };

    const handleClearCart = async () => {
        await clearCartUtil({
            onSuccess: (data) => {
                if (data.cart) {
                    setCart(data.cart);
                }
            },
        });
    };

    const incrementQuantity = (
        itemId: number | string,
        currentQuantity: string,
        sellByWeight: boolean = false,
        currentMultiplier: number = 1
    ) => {
        if (sellByWeight) {
            // For weight-based items, increment the multiplier
            updateWeightMultiplier(itemId, currentMultiplier + 1);
        } else {
            // For regular items, increment quantity directly
            const current = parseFloat(currentQuantity);
            const newQuantity = (current + 1).toString();
            updateQuantity(itemId, newQuantity);
        }
    };

    const decrementQuantity = (
        itemId: number | string,
        currentQuantity: string,
        sellByWeight: boolean = false,
        currentMultiplier: number = 1
    ) => {
        if (sellByWeight) {
            // For weight-based items, decrement the multiplier
            if (currentMultiplier > 1) {
                updateWeightMultiplier(itemId, currentMultiplier - 1);
            }
        } else {
            // For regular items, decrement quantity directly
            const current = parseFloat(currentQuantity);
            if (current > 1) {
                const newQuantity = (current - 1).toString();
                updateQuantity(itemId, newQuantity);
            }
        }
    };

    const updateWeightMultiplier = async (
        itemId: number | string,
        newMultiplier: number
    ) => {
        setUpdatingItems((prev) => new Set(prev).add(itemId));

        await updateCartItem(
            itemId,
            { weight_multiplier: newMultiplier },
            {
                onSuccess: (data) => {
                    if (data.cart) {
                        setCart(data.cart);
                    }
                },
                onFinally: () => {
                    setUpdatingItems((prev) => {
                        const newSet = new Set(prev);
                        newSet.delete(itemId);
                        return newSet;
                    });
                },
            }
        );
    };

    const handleWeightChange = async (
        itemId: number | string,
        newWeightValueId: string
    ) => {
        setUpdatingItems((prev) => new Set(prev).add(itemId));

        await updateCartItem(
            itemId,
            { weight_option_value_id: parseInt(newWeightValueId) },
            {
                onSuccess: (data) => {
                    if (data.cart) {
                        setCart(data.cart);
                    }
                },
                onFinally: () => {
                    setUpdatingItems((prev) => {
                        const newSet = new Set(prev);
                        newSet.delete(itemId);
                        return newSet;
                    });
                },
            }
        );
    };

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={t("cart")} />

            <div className="container mx-auto px-4 py-8 md:py-12">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-3xl md:text-4xl font-bold mb-2">
                            {t("cart")}
                        </h1>
                        <p className="text-muted-foreground">
                            {cart.items.length > 0
                                ? t("itemsInCart", {
                                    count: cart.items.length,
                                })
                                : t("emptyCart")}
                        </p>
                    </div>
                    <Link href={route("home")}>
                        <Button variant="outline" className="gap-2">
                            <BackArrowIcon className="h-4 w-4" />
                            {t("continueShopping")}
                        </Button>
                    </Link>
                </div>

                {cart.items.length === 0 ? (
                    <EmptyCart />
                ) : (
                    <div className="grid lg:grid-cols-3 gap-8">
                        {/* Cart Items */}
                        <div className="lg:col-span-2 space-y-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>
                                        {t("yourItems")} ({cart.items.length})
                                    </CardTitle>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleClearCart}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 className="h-4 w-4 ltr:mr-2 rtl:ml-2" />
                                        {t("clearCart")}
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {cart.items.map((item) => (
                                        <CartItem
                                            key={item.id}
                                            item={item}
                                            isUpdating={updatingItems.has(item.id)}
                                            onRemove={() => removeItem(item.id)}
                                            onIncrement={() =>
                                                incrementQuantity(
                                                    item.id,
                                                    item.quantity,
                                                    item.product?.sell_by_weight ?? false,
                                                    item.weight_multiplier
                                                )
                                            }
                                            onDecrement={() =>
                                                decrementQuantity(
                                                    item.id,
                                                    item.quantity,
                                                    item.product?.sell_by_weight ?? false,
                                                    item.weight_multiplier
                                                )
                                            }
                                            onWeightChange={(value) =>
                                                handleWeightChange(item.id, value)
                                            }
                                            formatCurrency={formatCurrency}
                                        />
                                    ))}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-1">
                            <CartSummary
                                cart={cart}
                                formatCurrency={formatCurrency}
                            />
                        </div>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}
