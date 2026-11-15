import React, { useState } from "react";
import { Link } from "react-router-dom";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
    ArrowRight,
    ArrowLeft,
    Trash2,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import {
    useCart,
    useUpdateCartItem,
    useRemoveCartItem,
    useClearCart,
    useAddToCart,
} from "@/plugins/cart-engine/api";
import { useFormatMoney } from "@/lib/money";
import CartItem from "./components/CartItem";
import CartSummary from "./components/CartSummary";
import EmptyCart from "./components/EmptyCart";
import { CartSkeleton } from "@/components/ui/page-skeletons";

export default function ThemedCart() {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const BackArrowIcon = isRTL ? ArrowRight : ArrowLeft;

    const { data: cart, isLoading, error } = useCart();
    const updateItemMutation = useUpdateCartItem();
    const removeItemMutation = useRemoveCartItem();
    const clearCartMutation = useClearCart();
    const addToCartMutation = useAddToCart({ suppressToast: true, track: false });

    const [updatingItems, setUpdatingItems] = useState<Set<number | string>>(new Set());
    const [isClearDialogOpen, setIsClearDialogOpen] = useState(false);
    const formatMoney = useFormatMoney();

    const handleUpdateQuantity = (itemId: number | string, newQuantity: string) => {
        if (parseFloat(newQuantity) < 0.001) return;

        setUpdatingItems((prev) => new Set(prev).add(itemId));
        updateItemMutation.mutate(
            { itemId, payload: { quantity: newQuantity } },
            {
                onSettled: () => {
                    setUpdatingItems((prev) => {
                        const next = new Set(prev);
                        next.delete(itemId);
                        return next;
                    });
                },
            }
        );
    };

    const handleIncrement = (
        itemId: number | string,
        currentQuantity: number | string,
        sellByWeight: boolean = false,
        currentMultiplier: number = 1
    ) => {
        if (sellByWeight) {
            handleUpdateWeightMultiplier(itemId, currentMultiplier + 1);
        } else {
            const current = typeof currentQuantity === 'number' ? currentQuantity : parseFloat(currentQuantity);
            handleUpdateQuantity(itemId, (current + 1).toString());
        }
    };

    const handleDecrement = (
        itemId: number | string,
        currentQuantity: number | string,
        sellByWeight: boolean = false,
        currentMultiplier: number = 1
    ) => {
        if (sellByWeight) {
            if (currentMultiplier > 1) {
                handleUpdateWeightMultiplier(itemId, currentMultiplier - 1);
            }
        } else {
            const current = typeof currentQuantity === 'number' ? currentQuantity : parseFloat(currentQuantity);
            if (current > 1) {
                handleUpdateQuantity(itemId, (current - 1).toString());
            }
        }
    };

    const handleUpdateWeightMultiplier = (itemId: number | string, newMultiplier: number) => {
        setUpdatingItems((prev) => new Set(prev).add(itemId));
        updateItemMutation.mutate(
            { itemId, payload: { weight_multiplier: newMultiplier } },
            {
                onSettled: () => {
                    setUpdatingItems((prev) => {
                        const next = new Set(prev);
                        next.delete(itemId);
                        return next;
                    });
                },
            }
        );
    };

    const handleWeightChange = (itemId: number | string, newWeightValueId: string) => {
        setUpdatingItems((prev) => new Set(prev).add(itemId));
        updateItemMutation.mutate(
            { itemId, payload: { weight_option_value_id: parseInt(newWeightValueId, 10) } },
            {
                onSettled: () => {
                    setUpdatingItems((prev) => {
                        const next = new Set(prev);
                        next.delete(itemId);
                        return next;
                    });
                },
            }
        );
    };

    const handleRemoveItem = (itemId: number | string) => {
        const itemToRemove = cart?.items.find((item) => item.id === itemId);
        if (!itemToRemove) return;

        const restorePayload = {
            product_id: itemToRemove.product_id,
            variant_id: itemToRemove.variant?.id ?? null,
            weight_option_value_id: itemToRemove.weight_option_value?.id ?? null,
            quantity: itemToRemove.quantity,
            weight_multiplier: itemToRemove.weight_multiplier ?? 1,
            extras: itemToRemove.extras?.map((e) => ({ id: e.id, quantity: e.quantity || 1 })),
        };

        const itemName = isRTL && itemToRemove.product?.name_ar
            ? itemToRemove.product.name_ar
            : itemToRemove.product?.name || "";

        setUpdatingItems((prev) => new Set(prev).add(itemId));
        removeItemMutation.mutate(itemId, {
            onSuccess: () => {
                const message = itemName
                    ? t("itemRemovedFromCart", { name: itemName, defaultValue: `Removed ${itemName} from cart` })
                    : t("itemRemovedFromCart", { defaultValue: "Item removed from cart" });

                toast(message, {
                    action: {
                        label: t("undo", "تراجع"),
                        onClick: () => {
                            addToCartMutation.mutate(restorePayload);
                        },
                    },
                });
            },
            onSettled: () => {
                setUpdatingItems((prev) => {
                    const next = new Set(prev);
                    next.delete(itemId);
                    return next;
                });
            },
        });
    };

    const handleClearCart = () => {
        setIsClearDialogOpen(true);
    };

    if (isLoading) {
        return <CartSkeleton />;
    }

    if (error || !cart) {
        return (
            <div className="container mx-auto px-4 py-16 text-center text-destructive">
                {t("errorLoadingCart", { defaultValue: "Failed to load cart. Please refresh or try again." })}
            </div>
        );
    }

    return (
        <div data-testid="cart-page" className="container mx-auto px-4 py-8 md:py-12">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div>
                    <h1 className="text-3xl md:text-4xl font-bold mb-2">
                        {t("cart")}
                    </h1>
                    <p className="text-muted-foreground">
                        {cart.items.length > 0
                            ? t("itemsInCart", { count: cart.items.length })
                            : t("emptyCart")}
                    </p>
                </div>
                <Link to="/">
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
                                    data-testid="clear-cart-button"
                                    onClick={handleClearCart}
                                    disabled={clearCartMutation.isPending}
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="h-4 w-4 me-2" />
                                    {t("clearCart")}
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {cart.items.map((item) => (
                                    <CartItem
                                        key={item.id}
                                        item={item as any}
                                        isUpdating={updatingItems.has(item.id)}
                                        onRemove={() => handleRemoveItem(item.id)}
                                        onIncrement={() =>
                                            handleIncrement(
                                                item.id,
                                                item.quantity,
                                                item.product?.sell_by_weight ?? false,
                                                item.weight_multiplier
                                            )
                                        }
                                        onDecrement={() =>
                                            handleDecrement(
                                                item.id,
                                                item.quantity,
                                                item.product?.sell_by_weight ?? false,
                                                item.weight_multiplier
                                            )
                                        }
                                        onWeightChange={(value) =>
                                            handleWeightChange(item.id, value)
                                        }
                                        formatCurrency={formatMoney}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Order Summary */}
                    <div className="lg:col-span-1">
                        <CartSummary
                            cart={cart as any}
                            formatCurrency={formatMoney}
                        />
                    </div>
                </div>
            )}

            {/* Clear Cart Confirmation Dialog */}
            <AlertDialog open={isClearDialogOpen} onOpenChange={setIsClearDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {t("confirmClearCartTitle", "مسح السلة")}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {t(
                                "confirmClearCartDescription",
                                "هل أنت متأكد من رغبتك في مسح السلة؟ سيتم حذف جميع المنتجات."
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>
                            {t("cancel", "إلغاء")}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            data-testid="confirm-clear-cart-action"
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => {
                                clearCartMutation.mutate();
                                setIsClearDialogOpen(false);
                            }}
                        >
                            {t("clearCart", "مسح السلة")}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
