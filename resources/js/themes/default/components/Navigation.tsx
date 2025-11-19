import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Search,
    ShoppingCart,
    User,
    Moon,
    Sun,
    Menu,
    Home,
    UtensilsCrossed,
    X,
    LogOut,
    Settings,
    Package
} from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { useTranslation } from 'react-i18next';

interface NavigationProps {
    categories?: Array<{ id: number; name: string; slug: string }>;
    cartItemsCount?: number;
}

export default function Navigation({ categories = [], cartItemsCount = 0 }: NavigationProps) {
    const { auth } = usePage().props as any;
    const { currentMode, setMode } = useTheme();
    const { t } = useTranslation();
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const toggleTheme = () => {
        setMode(currentMode === 'light' ? 'dark' : 'light');
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            // Handle search navigation
            window.location.href = `/menu?search=${encodeURIComponent(searchQuery)}`;
        }
    };

    return (
        <>
            {/* Desktop & Tablet Header */}
            <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="container mx-auto px-4">
                    <div className="flex h-16 items-center justify-between gap-4">
                        {/* Logo */}
                        <Link href="/" className="flex items-center gap-2 shrink-0">
                            <div className="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                <UtensilsCrossed className="h-6 w-6 text-primary-foreground" />
                            </div>
                            <span className="text-xl font-bold hidden sm:inline">{t('home')}</span>
                        </Link>

                        {/* Desktop Navigation Links - Hidden on Mobile */}
                        <nav className="hidden md:flex items-center gap-1">
                            <Link href="/">
                                <Button variant="ghost" className="gap-2">
                                    <Home className="h-4 w-4" />
                                    {t('home')}
                                </Button>
                            </Link>
                            <Link href="/menu">
                                <Button variant="ghost" className="gap-2">
                                    <UtensilsCrossed className="h-4 w-4" />
                                    {t('menu')}
                                </Button>
                            </Link>
                        </nav>

                        {/* Search Bar - Desktop */}
                        <div className="hidden lg:flex flex-1 max-w-md">
                            <form onSubmit={handleSearch} className="relative w-full">
                                <Search className="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder={t('search')}
                                    className="ltr:pl-10 rtl:pr-10 w-full"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                            </form>
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

                            {/* Theme Toggle */}
                            <Button variant="ghost" size="icon" onClick={toggleTheme}>
                                {currentMode === 'light' ? (
                                    <Moon className="h-5 w-5" />
                                ) : (
                                    <Sun className="h-5 w-5" />
                                )}
                            </Button>

                            {/* Language Switcher */}
                            <LanguageSwitcher />

                            {/* Cart */}
                            <Link href="/cart">
                                <Button variant="ghost" size="icon" className="relative">
                                    <ShoppingCart className="h-5 w-5" />
                                    {cartItemsCount > 0 && (
                                        <Badge className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                            {cartItemsCount > 9 ? '9+' : cartItemsCount}
                                        </Badge>
                                    )}
                                </Button>
                            </Link>

                            {/* User Menu */}
                            {auth?.user ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" className="rounded-full">
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage src={auth.user.avatar} />
                                                <AvatarFallback className="bg-primary text-primary-foreground">
                                                    {auth.user.name.charAt(0).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56">
                                        <div className="flex items-center gap-2 p-2">
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage src={auth.user.avatar} />
                                                <AvatarFallback className="bg-primary text-primary-foreground">
                                                    {auth.user.name.charAt(0).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex flex-col">
                                                <p className="text-sm font-medium">{auth.user.name}</p>
                                                <p className="text-xs text-muted-foreground">{auth.user.email}</p>
                                            </div>
                                        </div>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href="/profile" className="cursor-pointer">
                                                <User className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t('profile')}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link href="/orders" className="cursor-pointer">
                                                <Package className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t('myOrders')}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link href="/settings" className="cursor-pointer">
                                                <Settings className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t('settings')}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href="/logout" method="post" as="button" className="w-full cursor-pointer">
                                                <LogOut className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                {t('logout')}
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : (
                                <Link href="/login">
                                    <Button variant="default" size="sm" className="gap-2">
                                        <User className="h-4 w-4" />
                                        <span className="hidden sm:inline">{t('login')}</span>
                                    </Button>
                                </Link>
                            )}

                            {/* Mobile Menu Toggle */}
                            <Sheet>
                                <SheetTrigger asChild className="md:hidden">
                                    <Button variant="ghost" size="icon">
                                        <Menu className="h-5 w-5" />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="right" className="w-[300px]">
                                    <nav className="flex flex-col gap-4 mt-8">
                                        <Link href="/" className="flex items-center gap-2 text-lg font-medium hover:text-primary transition-colors">
                                            <Home className="h-5 w-5" />
                                            {t('home')}
                                        </Link>
                                        <Link href="/menu" className="flex items-center gap-2 text-lg font-medium hover:text-primary transition-colors">
                                            <UtensilsCrossed className="h-5 w-5" />
                                            Menu
                                        </Link>

                                        {categories.length > 0 && (
                                            <>
                                                <div className="my-2 border-t" />
                                                <p className="text-sm font-semibold text-muted-foreground">{t('categories')}</p>
                                                {categories.slice(0, 5).map((category) => (
                                                    <Link
                                                        key={category.id}
                                                        href={`/menu/${category.slug}`}
                                                        className="ltr:pl-4 rtl:pr-4 text-sm hover:text-primary transition-colors"
                                                    >
                                                        {category.name}
                                                    </Link>
                                                ))}
                                            </>
                                        )}
                                    </nav>
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
                                    placeholder={t('search')}
                                    className="ltr:pl-10 rtl:pr-10 ltr:pr-10 rtl:pl-10"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    autoFocus
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
                            </form>
                        </div>
                    )}
                </div>
            </header>

            {/* Mobile Bottom Navigation */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 safe-bottom">
                <div className="grid grid-cols-4 gap-1 p-2">
                    <Link href="/">
                        <Button variant="ghost" className="flex-col h-auto py-2 w-full gap-1">
                            <Home className="h-5 w-5" />
                            <span className="text-xs">{t('home')}</span>
                        </Button>
                    </Link>
                    <Link href="/menu">
                        <Button variant="ghost" className="flex-col h-auto py-2 w-full gap-1">
                            <UtensilsCrossed className="h-5 w-5" />
                            <span className="text-xs">{t('menu')}</span>
                        </Button>
                    </Link>
                    <Link href="/cart">
                        <Button variant="ghost" className="flex-col h-auto py-2 w-full gap-1 relative">
                            <ShoppingCart className="h-5 w-5" />
                            {cartItemsCount > 0 && (
                                <Badge className="absolute top-1 ltr:right-4 rtl:left-4 h-4 w-4 flex items-center justify-center p-0 text-[10px]">
                                    {cartItemsCount > 9 ? '9+' : cartItemsCount}
                                </Badge>
                            )}
                            <span className="text-xs">{t('cart')}</span>
                        </Button>
                    </Link>
                    <Link href={auth?.user ? "/profile" : "/login"}>
                        <Button variant="ghost" className="flex-col h-auto py-2 w-full gap-1">
                            <User className="h-5 w-5" />
                            <span className="text-xs">{auth?.user ? t('profile') : t('login')}</span>
                        </Button>
                    </Link>
                </div>
            </nav>
        </>
    );
}
