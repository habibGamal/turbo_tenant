import React, { useState, useEffect, useRef } from "react";
import { Link, useLocation } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Search, Home, ScrollText, X } from "lucide-react";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { useTranslation } from "react-i18next";
import { Logo } from "./navigation/Logo";
import { SearchBar } from "./navigation/SearchBar";
import { CartButton } from "./navigation/CartButton";
import { UserMenu } from "./navigation/UserMenu";
import { MobileMenu } from "./navigation/MobileMenu";
import { MobileBottomNav } from "./navigation/MobileBottomNav";
import { PluginSlot } from "@/core/slots/PluginSlot";
import { useTenantSafe } from "@/core/context/TenantContext";
import { useAuth } from "@/core/context/AuthContext";
import { useCart } from "@/plugins/cart-engine/api";
import { cn } from "@/lib/utils";

interface NavigationProps {
    categories?: Array<{ id: number; name: string; name_ar?: string }>;
    cartItemsCount?: number;
}

interface FooterLink {
    label: string;
    labelAr?: string;
    href: string;
}

interface FooterSection {
    title: string;
    titleAr?: string;
    links: FooterLink[];
}

export default function Navigation({
    categories = [],
    cartItemsCount = 0,
}: NavigationProps) {
    const { user } = useAuth();
    const tenantContext = useTenantSafe();
    const settings = tenantContext?.settings || {};
    const { data: cart } = useCart();
    const activeCartCount = cart?.items ? cart.items.length : (cartItemsCount || tenantContext?.cartCount || 0);
    const { t, i18n } = useTranslation();
    const location = useLocation();
    const pathname = location.pathname;
    const isMenuPage = pathname === "/menu";
    const [searchOpen, setSearchOpen] = useState(false);

    const isHomeActive = pathname === "/";
    const isMenuActive = pathname === "/menu" || pathname.startsWith("/menu/") || pathname.startsWith("/products/");
    const isTrackActive = pathname === "/track-order" || pathname === "/orders/track";

    const headerRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const el = headerRef.current;
        if (!el) return;

        const updateHeight = () => {
            const height = el.getBoundingClientRect().height;
            document.documentElement.style.setProperty("--header-h", `${height}px`);
        };

        updateHeight();

        if (typeof ResizeObserver !== "undefined") {
            const observer = new ResizeObserver(() => {
                updateHeight();
            });
            observer.observe(el);
            return () => {
                observer.disconnect();
            };
        }
    }, []);

    const getText = (text: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    const pages = settings?.pages || [];

    const footerSections: FooterSection[] = [
        {
            title: t("quickLinks"),
            titleAr: t("quickLinks"),
            links: [
                { label: t("home"), labelAr: t("home"), href: "/" },
                { label: t("menu"), labelAr: t("menu"), href: "/menu" },
                {
                    label: t("about"),
                    labelAr: t("about"),
                    href: "/pages/about-us",
                },
                {
                    label: t("contact"),
                    labelAr: t("contact"),
                    href: "/pages/contact-us",
                },
            ],
        },
        {
            title: t("support"),
            titleAr: t("support"),
            links: [
                {
                    label: t("deliveryInfo"),
                    labelAr: t("deliveryInfo"),
                    href: "/pages/delivery-policy",
                },
                {
                    label: t("trackOrder"),
                    labelAr: t("trackOrder"),
                    href: user ? "/orders" : "/track-order",
                },
                {
                    label: t("returns"),
                    labelAr: t("returns"),
                    href: "/pages/return-policy",
                },
            ],
        },
        {
            title: t("legal"),
            titleAr: t("legal"),
            links: pages.map((page: any) => ({
                label: page.title,
                labelAr: page.title_ar || page.title,
                href: `/pages/${page.slug}`,
            })),
        },
    ].filter((section) => section.links.length > 0);

    return (
        <>
            {/* Desktop & Tablet Header */}
            <header ref={headerRef} data-testid="theme-header" className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="container mx-auto px-4">
                    <div className="flex h-16 items-center justify-between gap-4">
                        {/* Logo */}
                        <Logo
                            siteLogo={settings?.site_logo}
                            siteName={settings?.site_name}
                            siteNameAr={settings?.site_name_ar}
                        />

                        {/* Desktop Navigation Links - Hidden on Mobile */}
                        <nav className="hidden md:flex items-center gap-1">
                            <Button
                                asChild
                                variant="ghost"
                                className={cn(
                                    "gap-2",
                                    isHomeActive && "bg-accent text-accent-foreground font-semibold"
                                )}
                            >
                                <Link to="/" aria-current={isHomeActive ? "page" : undefined}>
                                    <Home className="h-4 w-4" />
                                    {t("home")}
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="ghost"
                                className={cn(
                                    "gap-2",
                                    isMenuActive && "bg-accent text-accent-foreground font-semibold"
                                )}
                            >
                                <Link to="/menu" aria-current={isMenuActive ? "page" : undefined}>
                                    <ScrollText className="h-4 w-4" />
                                    {t("menu")}
                                </Link>
                            </Button>
                            {!user && (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className={cn(
                                        "gap-2",
                                        isTrackActive && "bg-accent text-accent-foreground font-semibold"
                                    )}
                                >
                                    <Link
                                        to="/track-order"
                                        aria-current={isTrackActive ? "page" : undefined}
                                    >
                                        <Search className="h-4 w-4" />
                                        {t("trackOrder")}
                                    </Link>
                                </Button>
                            )}
                        </nav>

                        {/* Search Bar - Desktop */}
                        <SearchBar className="hidden lg:flex flex-1 max-w-md relative group" />

                        {/* Actions */}
                        <div className="flex items-center gap-2">
                            {/* Search Button - Mobile/Tablet */}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="lg:hidden"
                                onClick={() => setSearchOpen(!searchOpen)}
                                aria-label={t("search", "Search")}
                            >
                                <Search className="h-5 w-5" />
                            </Button>

                            {/* Language Switcher */}
                            <LanguageSwitcher />

                            {/* Notifications */}
                            {user && (
                                <PluginSlot
                                    name="header.actions.notifications"
                                    pluginId="push-notifications"
                                />
                            )}

                            {/* Cart - Hidden on mobile viewports (< md) because MobileBottomNav provides bottom cart access */}
                            <div className="hidden md:flex items-center">
                                <PluginSlot
                                    name="header.actions.cart"
                                    pluginId="cart-engine"
                                    fallback={<CartButton />}
                                />
                            </div>

                            {/* User Menu */}
                            <UserMenu user={user} />

                            {/* Mobile Menu Toggle */}
                            <MobileMenu
                                categories={categories}
                                footerSections={footerSections}
                                settings={settings}
                            />
                        </div>
                    </div>

                    {/* Mobile Search Bar - Auto-expands on /menu, toggled on other pages */}
                    {(searchOpen || isMenuPage) && (
                        <div className="lg:hidden pb-4 animate-in slide-in-from-top-2 relative">
                            <SearchBar
                                autoFocus={searchOpen && !isMenuPage}
                                onClose={() => {
                                    if (!isMenuPage) {
                                        setSearchOpen(false);
                                    }
                                }}
                            />
                        </div>
                    )}
                </div>
            </header>

            {/* Mobile Bottom Navigation */}
            <MobileBottomNav user={user} cartItemsCount={activeCartCount} />
        </>
    );
}
