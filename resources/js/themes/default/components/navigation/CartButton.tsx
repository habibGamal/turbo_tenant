import React, { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ShoppingCart } from "lucide-react";
import { useCart } from "@/plugins/cart-engine/api";

export function CartButton() {
    const { t } = useTranslation();
    const { data: cart } = useCart();
    const [isBumped, setIsBumped] = useState(false);

    const cartCount = cart?.items.length ?? 0;

    useEffect(() => {
        if (cartCount > 0) {
            setIsBumped(true);
            const timer = setTimeout(() => setIsBumped(false), 300);
            return () => clearTimeout(timer);
        }
    }, [cartCount]);

    return (
        <Button
            asChild
            variant="ghost"
            size="icon"
            className={`relative transition-transform duration-300 ${isBumped ? "scale-125" : "scale-100"}`}
        >
            <Link
                to="/cart"
                data-testid="cart-trigger"
                aria-label={t("shoppingCart", "Shopping Cart")}
            >
                <ShoppingCart className="h-5 w-5" />
                {cartCount > 0 && (
                    <Badge data-testid="cart-badge-count" className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs animate-in zoom-in duration-300">
                        {cartCount > 9 ? "9+" : cartCount}
                    </Badge>
                )}
            </Link>
        </Button>
    );
}
