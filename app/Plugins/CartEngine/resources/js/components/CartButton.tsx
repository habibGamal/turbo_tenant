import React, { useState, useEffect } from "react";
import { Link, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ShoppingCart } from "lucide-react";

export function CartButton() {
    const { cartItemsCount: initialCartCount } = usePage().props as any;
    const [cartCount, setCartCount] = useState(initialCartCount || 0);
    const [isBumped, setIsBumped] = useState(false);

    useEffect(() => {
        setCartCount(initialCartCount || 0);
    }, [initialCartCount]);

    useEffect(() => {
        const handleCartUpdate = (event: CustomEvent) => {
            setCartCount(event.detail.count);
            setIsBumped(true);
            setTimeout(() => setIsBumped(false), 300);
        };

        window.addEventListener(
            "cart-updated",
            handleCartUpdate as EventListener,
        );
        return () => {
            window.removeEventListener(
                "cart-updated",
                handleCartUpdate as EventListener,
            );
        };
    }, []);

    return (
        <Link href="/cart">
            <Button
                variant="ghost"
                size="icon"
                className={`relative transition-transform duration-300 ${isBumped ? "scale-125" : "scale-100"}`}
            >
                <ShoppingCart className="h-5 w-5" />
                {cartCount > 0 && (
                    <Badge className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs animate-in zoom-in duration-300">
                        {cartCount > 9 ? "9+" : cartCount}
                    </Badge>
                )}
            </Button>
        </Link>
    );
}
