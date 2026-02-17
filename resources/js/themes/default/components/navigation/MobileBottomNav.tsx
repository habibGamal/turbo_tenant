import React from "react";
import { Link, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Home, ScrollText, ShoppingCart, Search, User } from "lucide-react";
import { useTranslation } from "react-i18next";

interface MobileBottomNavProps {
    user?: any;
    cartItemsCount?: number;
}

export function MobileBottomNav({ user, cartItemsCount = 0 }: MobileBottomNavProps) {
    const { t } = useTranslation();

    const handleAutoLogin = () => {
        router.post("/login", {
            email: "admin@example.com",
            password: "password",
        });
    };

    return (
        <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 safe-bottom">
            <div
                className={`grid gap-1 p-2 ${!user ? "grid-cols-5" : "grid-cols-4"}`}
            >
                <Link href="/">
                    <Button
                        variant="ghost"
                        className="flex-col h-auto py-2 w-full gap-1"
                    >
                        <Home className="h-5 w-5" />
                        <span className="text-xs">{t("home")}</span>
                    </Button>
                </Link>
                <Link href="/menu">
                    <Button
                        variant="ghost"
                        className="flex-col h-auto py-2 w-full gap-1"
                    >
                        <ScrollText className="h-5 w-5" />
                        <span className="text-xs">{t("menu")}</span>
                    </Button>
                </Link>
                <Link href="/cart">
                    <Button
                        variant="ghost"
                        className="flex-col h-auto py-2 w-full gap-1 relative"
                    >
                        <ShoppingCart className="h-5 w-5" />
                        {cartItemsCount > 0 && (
                            <Badge className="absolute top-1 ltr:right-4 rtl:left-4 h-4 w-4 flex items-center justify-center p-0 text-[10px]">
                                {cartItemsCount > 9 ? "9+" : cartItemsCount}
                            </Badge>
                        )}
                        <span className="text-xs">{t("cart")}</span>
                    </Button>
                </Link>
                {!user && (
                    <Link href="/track-order">
                        <Button
                            variant="ghost"
                            className="flex-col h-auto py-2 w-full gap-1"
                        >
                            <Search className="h-5 w-5" />
                            <span className="text-xs">{t("track")}</span>
                        </Button>
                    </Link>
                )}
                {user ? (
                    <Link href="/profile">
                        <Button
                            variant="ghost"
                            className="flex-col h-auto py-2 w-full gap-1"
                        >
                            <User className="h-5 w-5" />
                            <span className="text-xs">{t("profile")}</span>
                        </Button>
                    </Link>
                ) : (
                    <Button
                        variant="ghost"
                        className="flex-col h-auto py-2 w-full gap-1"
                        onClick={handleAutoLogin}
                    >
                        <User className="h-5 w-5" />
                        <span className="text-xs">{t("login")}</span>
                    </Button>
                )}
            </div>
        </nav>
    );
}
