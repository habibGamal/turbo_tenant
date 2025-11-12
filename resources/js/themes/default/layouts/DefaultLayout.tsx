import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
} from '@/components/ui/navigation-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Menu, ShoppingCart, User, Moon, Sun } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';

interface DefaultLayoutProps {
    children: React.ReactNode;
    categories?: Array<{ id: number; name: string; slug: string }>;
}

export default function DefaultLayout({ children, categories = [] }: DefaultLayoutProps) {
    const { auth } = usePage().props as any;
    const { currentMode, setMode } = useTheme();

    const toggleTheme = () => {
        setMode(currentMode === 'light' ? 'dark' : 'light');
    };

    return (
        <div className="min-h-screen bg-background">
            {/* Header */}
            <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="container mx-auto flex h-16 items-center justify-between px-4">
                    {/* Logo */}
                    <Link href="/" className="flex items-center space-x-2">
                        <div className="h-8 w-8 rounded-lg bg-primary" />
                        <span className="text-xl font-bold">Restaurant</span>
                    </Link>

                    {/* Desktop Navigation */}
                    <NavigationMenu className="hidden md:flex">
                        <NavigationMenuList>
                            <NavigationMenuItem>
                                <Link href="/">
                                    <NavigationMenuLink className="group inline-flex h-10 w-max items-center justify-center rounded-md bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none disabled:pointer-events-none disabled:opacity-50">
                                        Home
                                    </NavigationMenuLink>
                                </Link>
                            </NavigationMenuItem>

                            {categories.length > 0 && (
                                <NavigationMenuItem>
                                    <NavigationMenuTrigger>Menu</NavigationMenuTrigger>
                                    <NavigationMenuContent>
                                        <ul className="grid w-[400px] gap-3 p-4 md:w-[500px] md:grid-cols-2">
                                            {categories.map((category) => (
                                                <li key={category.id}>
                                                    <Link
                                                        href={`/menu/${category.slug}`}
                                                        className="block select-none space-y-1 rounded-md p-3 leading-none no-underline outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                                    >
                                                        <div className="text-sm font-medium leading-none">
                                                            {category.name}
                                                        </div>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </NavigationMenuContent>
                                </NavigationMenuItem>
                            )}

                            <NavigationMenuItem>
                                <Link href="/about">
                                    <NavigationMenuLink className="group inline-flex h-10 w-max items-center justify-center rounded-md bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none disabled:pointer-events-none disabled:opacity-50">
                                        About
                                    </NavigationMenuLink>
                                </Link>
                            </NavigationMenuItem>

                            <NavigationMenuItem>
                                <Link href="/contact">
                                    <NavigationMenuLink className="group inline-flex h-10 w-max items-center justify-center rounded-md bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none disabled:pointer-events-none disabled:opacity-50">
                                        Contact
                                    </NavigationMenuLink>
                                </Link>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>

                    {/* Actions */}
                    <div className="flex items-center space-x-4">
                        <Button variant="ghost" size="icon" onClick={toggleTheme}>
                            {currentMode === 'light' ? (
                                <Moon className="h-5 w-5" />
                            ) : (
                                <Sun className="h-5 w-5" />
                            )}
                        </Button>

                        <Link href="/cart">
                            <Button variant="ghost" size="icon" className="relative">
                                <ShoppingCart className="h-5 w-5" />
                                <span className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground">
                                    0
                                </span>
                            </Button>
                        </Link>

                        {auth?.user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon">
                                        <Avatar className="h-8 w-8">
                                            <AvatarImage src={auth.user.avatar} />
                                            <AvatarFallback>
                                                {auth.user.name.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem asChild>
                                        <Link href="/profile">Profile</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href="/orders">My Orders</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href="/logout" method="post" as="button">
                                            Logout
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <Link href="/login">
                                <Button variant="ghost" size="icon">
                                    <User className="h-5 w-5" />
                                </Button>
                            </Link>
                        )}

                        {/* Mobile Menu */}
                        <Sheet>
                            <SheetTrigger asChild className="md:hidden">
                                <Button variant="ghost" size="icon">
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="right">
                                <nav className="flex flex-col space-y-4 mt-8">
                                    <Link
                                        href="/"
                                        className="text-lg font-medium hover:text-primary"
                                    >
                                        Home
                                    </Link>
                                    <Link
                                        href="/menu"
                                        className="text-lg font-medium hover:text-primary"
                                    >
                                        Menu
                                    </Link>
                                    <Link
                                        href="/about"
                                        className="text-lg font-medium hover:text-primary"
                                    >
                                        About
                                    </Link>
                                    <Link
                                        href="/contact"
                                        className="text-lg font-medium hover:text-primary"
                                    >
                                        Contact
                                    </Link>
                                </nav>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="flex-1">{children}</main>

            {/* Footer */}
            <footer className="border-t bg-muted/50">
                <div className="container mx-auto px-4 py-12">
                    <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">About Us</h3>
                            <p className="text-sm text-muted-foreground">
                                Delivering delicious food right to your doorstep.
                            </p>
                        </div>
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">Quick Links</h3>
                            <ul className="space-y-2 text-sm">
                                <li>
                                    <Link href="/menu" className="text-muted-foreground hover:text-foreground">
                                        Menu
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/about" className="text-muted-foreground hover:text-foreground">
                                        About
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/contact" className="text-muted-foreground hover:text-foreground">
                                        Contact
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">Support</h3>
                            <ul className="space-y-2 text-sm">
                                <li>
                                    <Link href="/faq" className="text-muted-foreground hover:text-foreground">
                                        FAQ
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/privacy" className="text-muted-foreground hover:text-foreground">
                                        Privacy Policy
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/terms" className="text-muted-foreground hover:text-foreground">
                                        Terms of Service
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">Contact</h3>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                <li>123 Restaurant St.</li>
                                <li>City, State 12345</li>
                                <li>Phone: (555) 123-4567</li>
                                <li>Email: info@restaurant.com</li>
                            </ul>
                        </div>
                    </div>
                    <div className="mt-8 border-t pt-8 text-center text-sm text-muted-foreground">
                        © {new Date().getFullYear()} Restaurant. All rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
