import React from 'react';
import { ShoppingCart } from 'lucide-react';
import { useCart } from '../api/useCart';

export default function DefaultCartButton() {
    const { data: cart } = useCart();
    const count = cart?.items.length ?? 0;

    return (
        <a
            href="/cart"
            data-testid="cart-trigger"
            className="relative inline-flex items-center justify-center p-2 rounded-md hover:bg-accent text-foreground"
            aria-label="Shopping Cart"
        >
            <ShoppingCart className="w-5 h-5" />
            {count > 0 && (
                <span data-testid="cart-badge-count" className="absolute -top-1 -end-1 flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-primary rounded-full">
                    {count > 9 ? '9+' : count}
                </span>
            )}
        </a>
    );
}
