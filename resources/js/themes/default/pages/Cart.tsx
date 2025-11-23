import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import Navigation from "@/themes/default/components/Navigation";
import Footer from "@/themes/default/components/Footer";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Minus,
    Plus,
    X,
    ShoppingBag,
    ArrowRight,
    ArrowLeft,
    Trash2,
    Package,
    Copy,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Cart as CartType, PageProps } from "@/types";
import {
    updateCartItem,
    removeCartItem,
    clearCart as clearCartUtil,
    addToCart,
} from "@/utils/cartUtils";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

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

    const ArrowIcon = isRTL ? ArrowLeft : ArrowRight;
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
                        console.log("Updating cart after weight multiplier change:", data.cart);
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
    console.log("Rendering Cart with items:", cart.items);


    return (
        <>
            <Head title={t("cart")} />
            <div className="min-h-screen bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
                <Navigation />

                <main className="container mx-auto px-4 py-8 md:py-12">
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
                        /* Empty Cart State */
                        <Card className="py-16">
                            <CardContent className="flex flex-col items-center gap-6">
                                <div className="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center">
                                    <ShoppingBag className="w-12 h-12 text-primary" />
                                </div>
                                <div className="text-center space-y-2">
                                    <h2 className="text-2xl font-semibold">
                                        {t("cartEmpty")}
                                    </h2>
                                    <p className="text-muted-foreground">
                                        {t("cartEmptyDescription")}
                                    </p>
                                </div>
                                <Link href={route("home")}>
                                    <Button size="lg" className="gap-2">
                                        {t("startShopping")}
                                        <ArrowIcon className="h-4 w-4" />
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid lg:grid-cols-3 gap-8">
                            {/* Cart Items */}
                            <div className="lg:col-span-2 space-y-4">
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between">
                                        <CardTitle>
                                            {t("yourItems")} (
                                            {cart.items.length})
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
                                            <div key={item.id}>
                                                <div className="flex gap-4">
                                                    {/* Product Image */}
                                                    <Link
                                                        href={route(
                                                            "products.show",
                                                            item.product_id
                                                        )}
                                                        className="shrink-0"
                                                    >
                                                        <div className="w-24 h-24 rounded-lg bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden">
                                                            {item.product
                                                                ?.image ? (
                                                                <img
                                                                    src={
                                                                        item
                                                                            .product
                                                                            .image
                                                                    }
                                                                    alt={
                                                                        item
                                                                            .product
                                                                            .name ||
                                                                        t(
                                                                            "product"
                                                                        )
                                                                    }
                                                                    className="w-full h-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="absolute inset-0 flex items-center justify-center text-4xl">
                                                                    🍽️
                                                                </div>
                                                            )}
                                                        </div>
                                                    </Link>

                                                    {/* Product Info */}
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-start justify-between gap-2 mb-2">
                                                            <div className="flex-1 min-w-0">
                                                                <Link
                                                                    href={route(
                                                                        "products.show",
                                                                        item.product_id
                                                                    )}
                                                                >
                                                                    <h3 className="font-semibold text-lg leading-tight truncate hover:text-primary transition-colors">
                                                                        {item
                                                                            .product
                                                                            ?.name ||
                                                                            t(
                                                                                "unknownProduct"
                                                                            )}
                                                                    </h3>
                                                                </Link>
                                                                {item.variant && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="mt-1"
                                                                    >
                                                                        {
                                                                            item
                                                                                .variant
                                                                                .name
                                                                        }
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    removeItem(
                                                                        item.id
                                                                    )
                                                                }
                                                                disabled={updatingItems.has(
                                                                    item.id
                                                                )}
                                                                className="flex-shrink-0"
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </Button>
                                                        </div>

                                                        {/* Extras */}
                                                        {item.extras.length >
                                                            0 && (
                                                            <div className="text-sm text-muted-foreground mb-2 space-y-1">
                                                                {item.extras.map(
                                                                    (extra) => (
                                                                        <div
                                                                            key={
                                                                                extra.id
                                                                            }
                                                                            className="flex items-center gap-2"
                                                                        >
                                                                            <span className="text-xs">
                                                                                +
                                                                            </span>
                                                                            <span>
                                                                                {
                                                                                    extra.name
                                                                                }
                                                                                {extra.quantity > 1 && (
                                                                                    <span className="ltr:ml-1 rtl:mr-1">
                                                                                        × {extra.quantity}
                                                                                    </span>
                                                                                )}
                                                                            </span>
                                                                            <span className="ltr:ml-auto rtl:mr-auto">
                                                                                {formatCurrency(
                                                                                    extra.price * extra.quantity
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                    )
                                                                )}
                                                            </div>
                                                        )}

                                                        {/* Weight Selection (for weight-based products) */}
                                                        {item.product
                                                            ?.sell_by_weight &&
                                                            item.product
                                                                .weight_option && (
                                                                <div className="mb-2">
                                                                    <Label className="text-xs text-muted-foreground mb-1 block">
                                                                        {t(
                                                                            "weight"
                                                                        )}
                                                                    </Label>
                                                                    <Select
                                                                        value={
                                                                            item.weight_option_value_id?.toString() ||
                                                                            ""
                                                                        }
                                                                        onValueChange={(
                                                                            value
                                                                        ) =>
                                                                            handleWeightChange(
                                                                                item.id,
                                                                                value
                                                                            )
                                                                        }
                                                                        disabled={updatingItems.has(
                                                                            item.id
                                                                        )}
                                                                    >
                                                                        <SelectTrigger className="w-full">
                                                                            <SelectValue
                                                                                placeholder={t(
                                                                                    "selectWeight"
                                                                                )}
                                                                            />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {item.product.weight_option.values.map(
                                                                                (
                                                                                    weightValue
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            weightValue.id
                                                                                        }
                                                                                        value={weightValue.id.toString()}
                                                                                    >
                                                                                        {weightValue.label ||
                                                                                            `${
                                                                                                weightValue.value
                                                                                            } ${
                                                                                                item
                                                                                                    .product
                                                                                                    .weight_option!
                                                                                                    .unit
                                                                                            }`}
                                                                                    </SelectItem>
                                                                                )
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                            )}

                                                        {/* Quantity and Price */}
                                                        <div className="flex items-center justify-between gap-4">
                                                            <div className="flex items-center gap-2">
                                                                <Button
                                                                    variant="outline"
                                                                    size="icon"
                                                                    className="h-8 w-8"
                                                                    onClick={() =>
                                                                        decrementQuantity(
                                                                            item.id,
                                                                            item.quantity,
                                                                            item
                                                                                .product
                                                                                ?.sell_by_weight ?? false,
                                                                            item.weight_multiplier
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        updatingItems.has(
                                                                            item.id
                                                                        ) ||
                                                                        (item.product?.sell_by_weight
                                                                            ? item.weight_multiplier <= 1
                                                                            : parseFloat(item.quantity) <= 1
                                                                        )
                                                                    }
                                                                >
                                                                    <Minus className="h-3 w-3" />
                                                                </Button>
                                                                <div className="w-20 text-center font-medium">
                                                                    {item.product?.sell_by_weight ? (
                                                                        <>
                                                                            <span className="text-xs text-muted-foreground">×</span>
                                                                            {item.weight_multiplier}
                                                                        </>
                                                                    ) : (
                                                                        parseFloat(item.quantity).toFixed(0)
                                                                    )}
                                                                </div>
                                                                <Button
                                                                    variant="outline"
                                                                    size="icon"
                                                                    className="h-8 w-8"
                                                                    onClick={() =>
                                                                        incrementQuantity(
                                                                            item.id,
                                                                            item.quantity,
                                                                            item
                                                                                .product
                                                                                ?.sell_by_weight ?? false,
                                                                            item.weight_multiplier
                                                                        )
                                                                    }
                                                                    disabled={updatingItems.has(
                                                                        item.id
                                                                    )}
                                                                >
                                                                    <Plus className="h-3 w-3" />
                                                                </Button>
                                                            </div>
                                                            <div className="text-right ">
                                                                <div className="font-bold text-lg">
                                                                    {formatCurrency(
                                                                        item.subtotal
                                                                    )}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground space-y-0.5">
                                                                    {item.product?.sell_by_weight && item.weight_option_value ? (
                                                                        <>
                                                                            <div className="ltr">
                                                                                {parseFloat(item.quantity).toFixed(2)} {item.product.weight_option?.unit} × {formatCurrency(item.price)}
                                                                            </div>
                                                                            {item.extras_total > 0 && (
                                                                                <div>
                                                                                    + {t("extras")}: {formatCurrency(item.extras_total)} × {item.weight_multiplier}
                                                                                </div>
                                                                            )}
                                                                        </>
                                                                    ) : (
                                                                        <div className="ltr">
                                                                            {formatCurrency(
                                                                                item.price +
                                                                                    item.extras_total
                                                                            )}{" "}
                                                                            × {parseFloat(item.quantity).toFixed(0)}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                                <Separator className="mt-4" />
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Order Summary */}
                            <div className="lg:col-span-1">
                                <Card className="sticky top-4">
                                    <CardHeader>
                                        <CardTitle>
                                            {t("orderSummary")}
                                        </CardTitle>
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
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t("deliveryFee")}
                                                </span>
                                                <span className="font-medium text-green-600 dark:text-green-400">
                                                    {t("free")}
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

                                        {/* Coupon Code */}
                                        <div className="space-y-2 pt-4">
                                            <Label htmlFor="coupon">
                                                {t("couponCode")}
                                            </Label>
                                            <div className="flex gap-2">
                                                <Input
                                                    id="coupon"
                                                    placeholder={t(
                                                        "enterCouponCode"
                                                    )}
                                                />
                                                <Button variant="outline">
                                                    {t("apply")}
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                    <CardFooter className="flex-col gap-3">
                                        <Link href={route("checkout")} className="w-full">
                                            <Button
                                                className="w-full gap-2"
                                                size="lg"
                                            >
                                                {t("proceedToCheckout")}
                                                <ArrowIcon className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Package className="h-4 w-4" />
                                            <span>
                                                {t("freeDeliveryMessage")}
                                            </span>
                                        </div>
                                    </CardFooter>
                                </Card>
                            </div>
                        </div>
                    )}
                </main>

                <Footer />
            </div>
        </>
    );
}
