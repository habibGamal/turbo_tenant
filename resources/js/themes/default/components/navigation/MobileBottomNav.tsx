import React from "react";
import { Link, useLocation } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Home, ScrollText, ShoppingCart, Search, User } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useCart } from "@/plugins/cart-engine/api";
import { cn } from "@/lib/utils";

interface MobileBottomNavProps {
    user?: any;
    cartItemsCount?: number;
}

export function MobileBottomNav({ user, cartItemsCount = 0 }: MobileBottomNavProps) {
    const { t } = useTranslation();
    const location = useLocation();
    const pathname = location.pathname;
    const { data: cart } = useCart();

    if (pathname === "/checkout" || pathname.startsWith("/checkout/")) {
        return null;
    }

    const liveCartCount = cart?.items ? cart.items.length : cartItemsCount;

    const isHomeActive = pathname === "/";
    const isMenuActive = pathname === "/menu" || pathname.startsWith("/menu/") || pathname.startsWith("/products/");
    const isCartActive = pathname === "/cart";
    const isTrackActive = pathname === "/track-order" || pathname === "/orders/track" || pathname.startsWith("/orders");
    const isProfileActive = pathname === "/profile" || pathname.startsWith("/profile");

    const navItemClass = (isActive: boolean) =>
        cn(
            "flex flex-col items-center justify-center h-full w-full py-1 px-1 transition-all gap-1 relative select-none rounded-lg",
            isActive
                ? "text-primary font-semibold"
                : "text-muted-foreground hover:text-foreground active:scale-95 transition-transform"
        );

    return (
        <nav
            aria-label={t("bottomNavigation", "Mobile Navigation")}
            className="md:hidden fixed bottom-0 inset-x-0 z-40 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 safe-bottom shadow-[0_-1px_3px_rgba(0,0,0,0.05)]"
        >
            <div className="grid grid-cols-4 h-14 sm:h-16 w-full max-w-lg mx-auto items-stretch px-1">
                <Link
                    to="/"
                    aria-current={isHomeActive ? "page" : undefined}
                    className={navItemClass(isHomeActive)}
                >
                    <div className="relative flex items-center justify-center h-5 w-5 shrink-0">
                        <Home className="h-5 w-5" />
                    </div>
                    <span className="text-[10px] sm:text-[11px] leading-tight font-medium text-center whitespace-nowrap truncate max-w-full px-0.5">
                        {t("home")}
                    </span>
                </Link>

                <Link
                    to="/menu"
                    aria-current={isMenuActive ? "page" : undefined}
                    className={navItemClass(isMenuActive)}
                >
                    <div className="relative flex items-center justify-center h-5 w-5 shrink-0">
                        <ScrollText className="h-5 w-5" />
                    </div>
                    <span className="text-[10px] sm:text-[11px] leading-tight font-medium text-center whitespace-nowrap truncate max-w-full px-0.5">
                        {t("menu")}
                    </span>
                </Link>

                <Link
                    to="/cart"
                    aria-current={isCartActive ? "page" : undefined}
                    className={navItemClass(isCartActive)}
                >
                    <div className="relative flex items-center justify-center h-5 w-5 shrink-0">
                        <ShoppingCart className="h-5 w-5" />
                        {liveCartCount > 0 && (
                            <Badge
                                data-testid="mobile-cart-badge-count"
                                className="absolute -top-1.5 -end-2.5 h-4 min-w-4 px-1 flex items-center justify-center p-0 text-[10px] font-bold animate-in zoom-in duration-200 shadow-sm"
                            >
                                {liveCartCount > 9 ? "9+" : liveCartCount}
                            </Badge>
                        )}
                    </div>
                    <span className="text-[10px] sm:text-[11px] leading-tight font-medium text-center whitespace-nowrap truncate max-w-full px-0.5">
                        {t("cart")}
                    </span>
                </Link>

                {user ? (
                    <Link
                        to="/profile"
                        aria-current={isProfileActive ? "page" : undefined}
                        className={navItemClass(isProfileActive)}
                    >
                        <div className="relative flex items-center justify-center h-5 w-5 shrink-0">
                            <User className="h-5 w-5" />
                        </div>
                        <span className="text-[10px] sm:text-[11px] leading-tight font-medium text-center whitespace-nowrap truncate max-w-full px-0.5">
                            {t("profile")}
                        </span>
                    </Link>
                ) : (
                    <Link
                        to="/track-order"
                        aria-current={isTrackActive ? "page" : undefined}
                        className={navItemClass(isTrackActive)}
                    >
                        <div className="relative flex items-center justify-center h-5 w-5 shrink-0">
                            <Search className="h-5 w-5" />
                        </div>
                        <span className="text-[10px] sm:text-[11px] leading-tight font-medium text-center whitespace-nowrap truncate max-w-full px-0.5">
                            {t("track")}
                        </span>
                    </Link>
                )}
            </div>
        </nav>
    );
}

export default MobileBottomNav;
