import React, { useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Search, Home, ScrollText, X } from "lucide-react";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { useTranslation } from "react-i18next";
import { Logo } from "./navigation/Logo";
import { SearchBar } from "./navigation/SearchBar";
import { NotificationDropdown } from "./navigation/NotificationDropdown";
import { CartButton } from "./navigation/CartButton";
import { UserMenu } from "./navigation/UserMenu";
import { MobileMenu } from "./navigation/MobileMenu";
import { MobileBottomNav } from "./navigation/MobileBottomNav";

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
    const { auth, settings } = usePage().props as any;
    const { t, i18n } = useTranslation();
    const [searchOpen, setSearchOpen] = useState(false);

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
                    href: "/orders",
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
    ];

    return (
        <>
            {/* Desktop & Tablet Header */}
            <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
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
                            <Link href="/">
                                <Button variant="ghost" className="gap-2">
                                    <Home className="h-4 w-4" />
                                    {t("home")}
                                </Button>
                            </Link>
                            <Link href="/menu">
                                <Button variant="ghost" className="gap-2">
                                    <ScrollText className="h-4 w-4" />
                                    {t("menu")}
                                </Button>
                            </Link>
                            {!auth?.user && (
                                <Link href="/track-order">
                                    <Button variant="ghost" className="gap-2">
                                        <Search className="h-4 w-4" />
                                        {t("trackOrder")}
                                    </Button>
                                </Link>
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
                            >
                                <Search className="h-5 w-5" />
                            </Button>

                            {/* Language Switcher */}
                            <LanguageSwitcher />

                            {/* Notifications */}
                            {auth?.user && <NotificationDropdown />}

                            {/* Cart */}
                            <CartButton />

                            {/* User Menu */}
                            <UserMenu user={auth?.user} />

                            {/* Mobile Menu Toggle */}
                            <MobileMenu
                                categories={categories}
                                footerSections={footerSections}
                                settings={settings}
                            />
                        </div>
                    </div>

                    {/* Mobile Search Bar */}
                    {searchOpen && (
                        <div className="lg:hidden pb-4 animate-in slide-in-from-top-2 relative">
                            <SearchBar
                                autoFocus
                                onClose={() => setSearchOpen(false)}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute ltr:right-1 rtl:left-1 top-1/2 -translate-y-1/2"
                                onClick={() => setSearchOpen(false)}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    )}
                </div>
            </header>

            {/* Mobile Bottom Navigation */}
            <MobileBottomNav user={auth?.user} cartItemsCount={cartItemsCount} />
        </>
    );
}
