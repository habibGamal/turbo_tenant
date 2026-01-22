import React, { useState, useEffect } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import axios from "axios";
import { Product } from "@/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { ImageWithFallback } from "@/components/ui/image";
import {
    Search,
    ShoppingCart,
    User,
    Sun,
    Menu,
    Home,
    ScrollText,
    X,
    LogOut,
    Settings,
    Package,
    Facebook,
    Instagram,
    Twitter,
    MapPin,
    Phone,
    Mail,
    Heart,
} from "lucide-react";
import { useTheme } from "@/contexts/ThemeContext";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { useTranslation } from "react-i18next";

interface NavigationProps {
    categories?: Array<{ id: number; name: string }>;
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
    const { auth, settings, cartItemsCount: initialCartCount } = usePage().props as any;
    const { currentMode, setMode } = useTheme();
    const { t, i18n } = useTranslation();
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState("");
    const [suggestions, setSuggestions] = useState<Product[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
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

        window.addEventListener('cart-updated', handleCartUpdate as EventListener);
        return () => {
            window.removeEventListener('cart-updated', handleCartUpdate as EventListener);
        };
    }, []);

    const toggleTheme = () => {
        setMode(currentMode === "light" ? "dark" : "light");
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get("/menu", { search: searchQuery });
            setSearchOpen(false);
            setShowSuggestions(false);
        }
    };

    const handleSearchChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const query = e.target.value;
        setSearchQuery(query);

        if (query.length >= 2) {
            try {
                const response = await axios.get("/api/search/suggestions", {
                    params: { query },
                });
                setSuggestions(response.data);
                setShowSuggestions(true);
            } catch (error) {
                console.error("Failed to fetch suggestions:", error);
            }
        } else {
            setSuggestions([]);
            setShowSuggestions(false);
        }
    };

    const formatPrice = (price: number) => {
        return `${Number(price).toFixed(2)} ${t("currency")}`;
    };

    const handleAutoLogin = () => {
        router.post("/login", {
            email: "admin@example.com",
            password: "password",
        });
    };

    const pages = settings?.pages || [];

    const footerSections: FooterSection[] = [
        {
            title: t('quickLinks'),
            titleAr: t('quickLinks'),
            links: [
                { label: t('home'), labelAr: t('home'), href: '/' },
                { label: t('menu'), labelAr: t('menu'), href: '/menu' },
                { label: t('about'), labelAr: t('about'), href: '/pages/about-us' },
                { label: t('contact'), labelAr: t('contact'), href: '/pages/contact-us' },
            ],
        },
        {
            title: t('support'),
            titleAr: t('support'),
            links: [
                { label: t('deliveryInfo'), labelAr: t('deliveryInfo'), href: '/pages/delivery-policy' },
                { label: t('trackOrder'), labelAr: t('trackOrder'), href: '/orders' },
                { label: t('returns'), labelAr: t('returns'), href: '/pages/return-policy' },
            ],
        },
        {
            title: t('legal'),
            titleAr: t('legal'),
            links: pages.map((page: any) => ({
                label: page.title,
                labelAr: page.title_ar || page.title,
                href: `/pages/${page.slug}`,
            })),
        },
    ];

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    return (
        <>
            {/* Desktop & Tablet Header */}
            <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="container mx-auto px-4">
                    <div className="flex h-16 items-center justify-between gap-4">
                        {/* Logo */}
                        <Link
                            href="/"
                            className="flex items-center gap-2 shrink-0"
                        >
                            {settings?.site_logo ? (
                                <img
                                    src={settings.site_logo}
                                    alt={settings?.site_name || t("home")}
                                    className="h-12 w-auto object-contain rounded"
                                />
                            ) : (
                                <div className="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                    <ScrollText className="h-6 w-6 text-primary-foreground" />
                                </div>
                            )}
                            <span className="text-xl font-bold hidden sm:inline">
                                {settings?.site_name || t("home")}
                            </span>
                        </Link>

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
                        </nav>

                        {/* Search Bar - Desktop */}
                        <div className="hidden lg:flex flex-1 max-w-md relative group">
                            <form
                                onSubmit={handleSearch}
                                className="relative w-full"
                            >
                                <Search className="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                                <Input
                                    type="search"
                                    placeholder={t("search")}
                                    className="ltr:pl-10 rtl:pr-10 w-full transition-all focus-visible:ring-primary/20"
                                    value={searchQuery}
                                    onChange={handleSearchChange}
                                    onFocus={() => suggestions.length > 0 && setShowSuggestions(true)}
                                    onBlur={() => setTimeout(() => setShowSuggestions(false), 200)}
                                />
                            </form>
                            {showSuggestions && suggestions.length > 0 && (
                                <Card className="absolute top-full left-0 right-0 mt-2 z-50 shadow-lg animate-in fade-in zoom-in-95 duration-200">
                                    <CardContent className="p-2">
                                        {suggestions.map((product) => (
                                            <div
                                                key={product.id}
                                                className="flex items-center gap-3 p-2 hover:bg-accent rounded-md cursor-pointer transition-colors"
                                                onClick={() => {
                                                    setSearchQuery(product.name);
                                                    setShowSuggestions(false);
                                                    router.visit(`/products/${product.id}`);
                                                }}
                                            >
                                                <ImageWithFallback
                                                    src={product.image}
                                                    alt={product.name}
                                                    className="w-10 h-10 object-cover rounded shrink-0"
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium truncate">
                                                        {product.name}
                                                    </p>
                                                    <div className="flex items-center gap-2">
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatPrice(product.price_after_discount ?? product.base_price ?? product.price)}
                                                        </p>
                                                        {product.category && typeof product.category === 'object' && (
                                                            <span className="text-[10px] text-muted-foreground bg-secondary px-1.5 py-0.5 rounded">
                                                                {product.category.name}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            )}
                        </div>

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

                            {/* Cart */}
                            <Link href="/cart">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className={`relative transition-transform duration-300 ${isBumped ? 'scale-125' : 'scale-100'}`}
                                >
                                    <ShoppingCart className="h-5 w-5" />
                                    {cartCount > 0 && (
                                        <Badge className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs animate-in zoom-in duration-300">
                                            {cartCount > 9
                                                ? "9+"
                                                : cartCount}
                                        </Badge>
                                    )}
                                </Button>
                            </Link>

                            {/* User Menu */}
                            {auth?.user ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="rounded-full"
                                        >
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage
                                                    src={auth.user.avatar}
                                                />
                                                <AvatarFallback className="bg-primary text-primary-foreground">
                                                    {auth.user.name
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-56"
                                    >
                                        <div className="flex items-center gap-2 p-2">
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage
                                                    src={auth.user.avatar}
                                                />
                                                <AvatarFallback className="bg-primary text-primary-foreground">
                                                    {auth.user.name
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex flex-col">
                                                <p className="text-sm font-medium">
                                                    {auth.user.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {auth.user.email}
                                                </p>
                                            </div>
                                        </div>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={route("profile.edit")}
                                                className="cursor-pointer"
                                            >
                                                <User className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t("profile")}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={route("favorites.index")}
                                                className="cursor-pointer"
                                            >
                                                <Heart className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t("myFavorites")}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href="/orders"
                                                className="cursor-pointer"
                                            >
                                                <Package className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t("myOrders")}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href="/logout"
                                                method="post"
                                                as="button"
                                                className="w-full cursor-pointer"
                                            >
                                                <LogOut className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t("logout")}
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : (
                                <Button
                                    variant="default"
                                    size="sm"
                                    className="gap-2"
                                    onClick={() => router.get("/login")}
                                >
                                    <User className="h-4 w-4" />
                                    <span className="hidden sm:inline">
                                        {t("login")}
                                    </span>
                                </Button>
                            )}

                            {/* Mobile Menu Toggle */}
                            <Sheet>
                                <SheetTrigger asChild className="md:hidden">
                                    <Button variant="ghost" size="icon">
                                        <Menu className="h-5 w-5" />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side="right"
                                    className="w-[300px] overflow-y-auto"
                                >
                                    <nav className="flex flex-col gap-4 mt-8">
                                        <Link
                                            href="/"
                                            className="flex items-center gap-2 text-lg font-medium hover:text-primary transition-colors"
                                        >
                                            <Home className="h-5 w-5" />
                                            {t("home")}
                                        </Link>
                                        <Link
                                            href="/menu"
                                            className="flex items-center gap-2 text-lg font-medium hover:text-primary transition-colors"
                                        >
                                            <ScrollText className="h-5 w-5" />
                                            {t("menu")}
                                        </Link>

                                        {categories.length > 0 && (
                                            <>
                                                <div className="my-2 border-t" />
                                                <p className="text-sm font-semibold text-muted-foreground">
                                                    {t("categories")}
                                                </p>
                                                {categories
                                                    .slice(0, 5)
                                                    .map((category) => (
                                                        <Link
                                                            key={category.id}
                                                            href={`/menu?category=${encodeURIComponent(category.name)}`}
                                                            className="ltr:pl-4 rtl:pr-4 text-sm hover:text-primary transition-colors"
                                                        >
                                                            {category.name}
                                                        </Link>
                                                    ))}
                                            </>
                                        )}
                                    </nav>

                                    <div className="my-4 border-t" />

                                    {/* Footer Content in Mobile Menu */}
                                    <div className="space-y-6 pb-8 px-1">
                                        {footerSections.map((section, index) => (
                                            <div key={index}>
                                                <h4 className="font-semibold text-sm mb-2 text-foreground">
                                                    {getText(section.title, section.titleAr)}
                                                </h4>
                                                <ul className="space-y-2">
                                                    {section.links.map((link, linkIndex) => (
                                                        <li key={linkIndex}>
                                                            <Link
                                                                href={link.href}
                                                                className="text-sm text-muted-foreground hover:text-primary transition-colors"
                                                            >
                                                                {getText(link.label, link.labelAr)}
                                                            </Link>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ))}

                                        {/* Contact Info */}
                                        <div>
                                            <h4 className="font-semibold text-sm mb-2 text-foreground">
                                                {t('contactUs')}
                                            </h4>
                                            <ul className="space-y-2 text-sm text-muted-foreground">
                                                {settings?.contact_address && (
                                                    <li className="flex items-start gap-2">
                                                        <MapPin className="h-4 w-4 shrink-0 mt-0.5" />
                                                        <span>{settings.contact_address}</span>
                                                    </li>
                                                )}
                                                {settings?.contact_phone && (
                                                    <li className="flex items-center gap-2">
                                                        <Phone className="h-4 w-4 shrink-0" />
                                                        <a href={`tel:${settings.contact_phone}`} className="hover:text-foreground">
                                                            {settings.contact_phone}
                                                        </a>
                                                    </li>
                                                )}
                                                {settings?.contact_email && (
                                                    <li className="flex items-center gap-2">
                                                        <Mail className="h-4 w-4 shrink-0" />
                                                        <a href={`mailto:${settings.contact_email}`} className="hover:text-foreground">
                                                            {settings.contact_email}
                                                        </a>
                                                    </li>
                                                )}
                                            </ul>
                                        </div>

                                        {/* Social Links */}
                                        <div className="flex gap-2">
                                            {settings?.social_facebook && (
                                                <Button variant="outline" size="icon" className="h-8 w-8" asChild>
                                                    <a href={settings.social_facebook} target="_blank" rel="noopener noreferrer">
                                                        <Facebook className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            )}
                                            {settings?.social_instagram && (
                                                <Button variant="outline" size="icon" className="h-8 w-8" asChild>
                                                    <a href={settings.social_instagram} target="_blank" rel="noopener noreferrer">
                                                        <Instagram className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            )}
                                            {settings?.social_twitter && (
                                                <Button variant="outline" size="icon" className="h-8 w-8" asChild>
                                                    <a href={settings.social_twitter} target="_blank" rel="noopener noreferrer">
                                                        <Twitter className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>

                                        {/* Copyright */}
                                        <p className="text-xs text-muted-foreground">
                                            © {new Date().getFullYear()} {settings?.site_name || t('home')}. {t('allRightsReserved')}.
                                        </p>
                                    </div>
                                </SheetContent>
                            </Sheet>
                        </div>
                    </div>

                    {/* Mobile Search Bar */}
                    {searchOpen && (
                        <div className="lg:hidden pb-4 animate-in slide-in-from-top-2">
                            <form onSubmit={handleSearch} className="relative">
                                <Search className="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder={t("search")}
                                    className="ltr:pl-10 rtl:pr-10 ltr:pr-10 rtl:pl-10"
                                    value={searchQuery}
                                    onChange={handleSearchChange}
                                    onFocus={() => suggestions.length > 0 && setShowSuggestions(true)}
                                    onBlur={() => setTimeout(() => setShowSuggestions(false), 200)}
                                    autoFocus
                                />
                                {showSuggestions && suggestions.length > 0 && (
                                    <Card className="absolute top-full left-0 right-0 mt-2 z-50 shadow-lg animate-in fade-in zoom-in-95 duration-200">
                                        <CardContent className="p-2">
                                            {suggestions.map((product) => (
                                                <div
                                                    key={product.id}
                                                    className="flex items-center gap-3 p-2 hover:bg-accent rounded-md cursor-pointer transition-colors"
                                                    onClick={() => {
                                                        setSearchQuery(product.name);
                                                        setShowSuggestions(false);
                                                        setSearchOpen(false);
                                                        router.visit(`/products/${product.id}`);
                                                    }}
                                                >
                                                    <ImageWithFallback
                                                        src={product.image}
                                                        alt={product.name}
                                                        className="w-10 h-10 object-cover rounded shrink-0"
                                                    />
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium truncate">
                                                            {product.name}
                                                        </p>
                                                        <div className="flex items-center gap-2">
                                                            <p className="text-xs text-muted-foreground">
                                                                {formatPrice(product.price_after_discount ?? product.base_price ?? product.price)}
                                                            </p>
                                                            {product.category && typeof product.category === 'object' && (
                                                                <span className="text-[10px] text-muted-foreground bg-secondary px-1.5 py-0.5 rounded">
                                                                    {product.category.name}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </CardContent>
                                    </Card>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="absolute ltr:right-1 rtl:left-1 top-1/2 -translate-y-1/2"
                                    onClick={() => setSearchOpen(false)}
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
            </header>

            {/* Mobile Bottom Navigation */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 safe-bottom">
                <div className="grid grid-cols-4 gap-1 p-2">
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
                    {auth?.user ? (
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
        </>
    );
}
