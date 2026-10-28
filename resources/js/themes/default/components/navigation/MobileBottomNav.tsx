import React from "react";
import { Link, useLocation } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Home, ScrollText, ShoppingCart, Search, User } from "lucide-react";
import { useTranslation } from "react-i18next";
import { cn } from "@/lib/utils";

interface MobileBottomNavProps {
    user?: any;
    cartItemsCount?: number;
}

export function MobileBottomNav({ user, cartItemsCount = 0 }: MobileBottomNavProps) {
    const { t } = useTranslation();
    const location = useLocation();
    const pathname = location.pathname;

    const isHomeActive = pathname === "/";
    const isMenuActive = pathname === "/menu" || pathname.startsWith("/menu/") || pathname.startsWith("/products/");
    const isCartActive = pathname === "/cart";
    const isTrackActive = pathname === "/track-order" || pathname === "/orders/track" || pathname.startsWith("/orders");
    const isProfileActive = pathname === "/profile" || pathname.startsWith("/profile");
    const isLoginActive = pathname === "/login" || pathname === "/register";

    const navItemClass = (isActive: boolean) =>
        cn(
            "flex flex-col items-center justify-center py-2 px-1 rounded-md text-xs transition-colors gap-1 relative",
            isActive
                ? "text-primary font-semibold"
                : "text-muted-foreground hover:text-foreground hover:bg-accent/50"
        );

    return (
        <nav
            aria-label={t("bottomNavigation", "Mobile Navigation")}
            className="md:hidden fixed bottom-0 inset-x-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 safe-bottom"
        >
            <div
                className={`grid gap-1 p-2 ${!user ? "grid-cols-5" : "grid-cols-4"}`}
            >
                <Link
                    to="/"
                    aria-current={isHomeActive ? "page" : undefined}
                    className={navItemClass(isHomeActive)}
                >
                    <Home className="h-5 w-5" />
                    <span className="text-xs">{t("home")}</span>
                </Link>

                <Link
                    to="/menu"
                    aria-current={isMenuActive ? "page" : undefined}
                    className={navItemClass(isMenuActive)}
                >
                    <ScrollText className="h-5 w-5" />
                    <span className="text-xs">{t("menu")}</span>
                </Link>

                <Link
                    to="/cart"
                    aria-current={isCartActive ? "page" : undefined}
                    className={navItemClass(isCartActive)}
                >
                    <div className="relative flex items-center justify-center">
                        <ShoppingCart className="h-5 w-5" />
                        {cartItemsCount > 0 && (
                            <Badge className="absolute -top-1.5 -end-3 h-4 min-w-4 px-1 flex items-center justify-center p-0 text-[10px]">
                                {cartItemsCount > 9 ? "9+" : cartItemsCount}
                            </Badge>
                        )}
                    </div>
                    <span className="text-xs">{t("cart")}</span>
                </Link>

                {!user && (
                    <Link
                        to="/track-order"
                        aria-current={isTrackActive ? "page" : undefined}
                        className={navItemClass(isTrackActive)}
                    >
                        <Search className="h-5 w-5" />
                        <span className="text-xs">{t("track")}</span>
                    </Link>
                )}

                {user ? (
                    <Link
                        to="/profile"
                        aria-current={isProfileActive ? "page" : undefined}
                        className={navItemClass(isProfileActive)}
                    >
                        <User className="h-5 w-5" />
                        <span className="text-xs">{t("profile")}</span>
                    </Link>
                ) : (
                    <Link
                        to="/login"
                        aria-current={isLoginActive ? "page" : undefined}
                        className={navItemClass(isLoginActive)}
                    >
                        <User className="h-5 w-5" />
                        <span className="text-xs">{t("login")}</span>
                    </Link>
                )}
            </div>
        </nav>
    );
}

export default MobileBottomNav;
