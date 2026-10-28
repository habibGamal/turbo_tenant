import React from 'react';
import { Trash2, Plus, Minus, ShoppingBag } from 'lucide-react';
import { useCart } from '../api/useCart';
import { useUpdateCartItem } from '../api/useUpdateCartItem';
import { useRemoveCartItem } from '../api/useRemoveCartItem';
import { useClearCart } from '../api/useClearCart';

export default function DefaultCart() {
    const { data: cart, isLoading, error } = useCart();
    const updateItemMutation = useUpdateCartItem();
    const removeItemMutation = useRemoveCartItem();
    const clearCartMutation = useClearCart();

    if (isLoading) {
        return (
            <div className="py-12 text-center text-sm text-muted-foreground animate-pulse">
                Loading cart...
            </div>
        );
    }

    if (error || !cart) {
        return (
            <div className="py-12 text-center text-sm text-destructive">
                Failed to load cart. Please try again.
            </div>
        );
    }

    if (cart.items.length === 0) {
        return (
            <div className="py-16 text-center max-w-md mx-auto">
                <ShoppingBag className="w-12 h-12 mx-auto text-muted-foreground mb-4" />
                <h2 className="text-xl font-semibold mb-2">Your cart is empty</h2>
                <p className="text-sm text-muted-foreground mb-6">Looks like you haven't added any items to your cart yet.</p>
                <a
                    href="/"
                    className="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90"
                >
                    Start Shopping
                </a>
            </div>
        );
    }

    return (
        <div className="w-full max-w-4xl mx-auto p-4 space-y-6">
            <div className="flex items-center justify-between border-b pb-4">
                <h1 className="text-2xl font-bold">Shopping Cart ({cart.items.length})</h1>
                <button
                    type="button"
                    onClick={() => clearCartMutation.mutate()}
                    disabled={clearCartMutation.isPending}
                    className="text-sm text-destructive hover:underline flex items-center gap-1 disabled:opacity-50"
                >
                    <Trash2 className="w-4 h-4" />
                    Clear Cart
                </button>
            </div>

            <div className="space-y-4">
                {cart.items.map((item) => {
                    const currentQty = parseFloat(item.quantity);
                    const isUpdating = updateItemMutation.isPending || removeItemMutation.isPending;

                    return (
                        <div
                            key={item.id}
                            className="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 border rounded-lg gap-4 bg-card text-card-foreground"
                        >
                            <div className="flex-1">
                                <h3 className="font-semibold text-base">{item.product.name}</h3>
                                {item.variant && (
                                    <p className="text-xs text-muted-foreground">
                                        Variant: {item.variant.name}
                                    </p>
                                )}
                                {item.extras && item.extras.length > 0 && (
                                    <div className="text-xs text-muted-foreground mt-1">
                                        Extras: {item.extras.map((e) => e.name).join(', ')}
                                    </div>
                                )}
                                <div className="text-sm font-medium mt-1">
                                    ${Number(item.price).toFixed(2)}
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <div className="flex items-center border rounded-md">
                                    <button
                                        type="button"
                                        disabled={isUpdating || currentQty <= 1}
                                        onClick={() =>
                                            updateItemMutation.mutate({
                                                itemId: item.id,
                                                payload: { quantity: String(Math.max(1, currentQty - 1)) },
                                            })
                                        }
                                        className="p-1.5 hover:bg-muted disabled:opacity-30"
                                        aria-label="Decrease quantity"
                                    >
                                        <Minus className="w-4 h-4" />
                                    </button>
                                    <span className="px-3 text-sm font-medium">{item.quantity}</span>
                                    <button
                                        type="button"
                                        disabled={isUpdating}
                                        onClick={() =>
                                            updateItemMutation.mutate({
                                                itemId: item.id,
                                                payload: { quantity: String(currentQty + 1) },
                                            })
                                        }
                                        className="p-1.5 hover:bg-muted disabled:opacity-30"
                                        aria-label="Increase quantity"
                                    >
                                        <Plus className="w-4 h-4" />
                                    </button>
                                </div>

                                <div className="text-right min-w-[5rem]">
                                    <div className="font-bold text-sm">
                                        ${Number(item.subtotal).toFixed(2)}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    disabled={isUpdating}
                                    onClick={() => removeItemMutation.mutate(item.id)}
                                    className="p-1.5 text-muted-foreground hover:text-destructive"
                                    aria-label="Remove item"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="text-lg font-bold">
                    Total: <span className="text-primary">${Number(cart.total).toFixed(2)}</span>
                </div>
                <a
                    href="/checkout"
                    className="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-md bg-primary text-primary-foreground font-medium hover:bg-primary/90"
                >
                    Proceed to Checkout
                </a>
            </div>
        </div>
    );
}
