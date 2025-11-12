import React from 'react';
import DefaultLayout from '@/themes/default/layouts/DefaultLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Link } from '@inertiajs/react';
import { Clock, Star, ChefHat, Truck, Heart, ShoppingCart } from 'lucide-react';

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    image?: string;
    category: string;
    rating?: number;
    is_featured?: boolean;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    image?: string;
}

interface HomePageProps {
    categories?: Category[];
    featuredProducts?: Product[];
    popularProducts?: Product[];
}

export default function HomePage({ categories = [], featuredProducts = [], popularProducts = [] }: HomePageProps) {
    return (
        <DefaultLayout categories={categories}>
            {/* Hero Section */}
            <section className="relative bg-gradient-to-br from-primary/10 via-background to-secondary/10 py-20 md:py-32">
                <div className="container mx-auto px-4">
                    <div className="grid gap-8 md:grid-cols-2 items-center">
                        <div className="space-y-6">
                            <Badge className="w-fit">Fresh & Delicious</Badge>
                            <h1 className="text-4xl font-bold tracking-tight md:text-6xl">
                                Your Favorite Food,
                                <span className="text-primary"> Delivered Fast</span>
                            </h1>
                            <p className="text-lg text-muted-foreground md:text-xl">
                                Experience the finest cuisine from our kitchen to your table. Order now and enjoy
                                restaurant-quality meals in the comfort of your home.
                            </p>
                            <div className="flex flex-col gap-4 sm:flex-row">
                                <Link href="/menu">
                                    <Button size="lg" className="w-full sm:w-auto">
                                        Order Now
                                    </Button>
                                </Link>
                                <Link href="/menu">
                                    <Button size="lg" variant="outline" className="w-full sm:w-auto">
                                        View Menu
                                    </Button>
                                </Link>
                            </div>
                        </div>
                        <div className="relative aspect-square md:aspect-auto md:h-[500px]">
                            <div className="absolute inset-0 rounded-2xl bg-gradient-to-br from-primary/20 to-secondary/20" />
                            <div className="relative flex h-full items-center justify-center">
                                <ChefHat className="h-48 w-48 text-primary/40" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Features Section */}
            <section className="py-16 md:py-24">
                <div className="container mx-auto px-4">
                    <div className="grid gap-8 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <Clock className="h-12 w-12 text-primary mb-4" />
                                <CardTitle>Fast Delivery</CardTitle>
                                <CardDescription>
                                    Get your food delivered hot and fresh within 30 minutes
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader>
                                <ChefHat className="h-12 w-12 text-primary mb-4" />
                                <CardTitle>Expert Chefs</CardTitle>
                                <CardDescription>
                                    Our experienced chefs prepare every dish with passion
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader>
                                <Star className="h-12 w-12 text-primary mb-4" />
                                <CardTitle>Quality Ingredients</CardTitle>
                                <CardDescription>
                                    We use only the freshest, highest-quality ingredients
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </div>
            </section>

            {/* Categories Section */}
            {categories.length > 0 && (
                <>
                    <Separator />
                    <section className="py-16 md:py-24">
                        <div className="container mx-auto px-4">
                            <div className="mb-12 text-center">
                                <h2 className="mb-4 text-3xl font-bold tracking-tight md:text-4xl">
                                    Browse Our Menu
                                </h2>
                                <p className="text-lg text-muted-foreground">
                                    Explore our diverse range of delicious dishes
                                </p>
                            </div>
                            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {categories.slice(0, 8).map((category) => (
                                    <Link key={category.id} href={`/menu/${category.slug}`}>
                                        <Card className="group cursor-pointer overflow-hidden transition-all hover:shadow-lg">
                                            <div className="aspect-video bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                                                <ChefHat className="h-16 w-16 text-primary/40 group-hover:scale-110 transition-transform" />
                                            </div>
                                            <CardHeader>
                                                <CardTitle>{category.name}</CardTitle>
                                                {category.description && (
                                                    <CardDescription>{category.description}</CardDescription>
                                                )}
                                            </CardHeader>
                                        </Card>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>
                </>
            )}

            {/* Featured Products Section */}
            {featuredProducts.length > 0 && (
                <>
                    <Separator />
                    <section className="py-16 md:py-24 bg-muted/30">
                        <div className="container mx-auto px-4">
                            <div className="mb-12 text-center">
                                <h2 className="mb-4 text-3xl font-bold tracking-tight md:text-4xl">
                                    Featured Dishes
                                </h2>
                                <p className="text-lg text-muted-foreground">
                                    Our chef's special recommendations
                                </p>
                            </div>
                            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {featuredProducts.map((product) => (
                                    <Card key={product.id} className="group overflow-hidden">
                                        <div className="aspect-square bg-gradient-to-br from-primary/10 to-secondary/10 relative overflow-hidden">
                                            <div className="absolute inset-0 flex items-center justify-center">
                                                <ChefHat className="h-24 w-24 text-primary/40" />
                                            </div>
                                            <Button
                                                size="icon"
                                                variant="secondary"
                                                className="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity"
                                            >
                                                <Heart className="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="space-y-1">
                                                    <CardTitle>{product.name}</CardTitle>
                                                    <Badge variant="secondary">{product.category}</Badge>
                                                </div>
                                            </div>
                                            <CardDescription className="line-clamp-2">
                                                {product.description}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardFooter className="flex items-center justify-between">
                                            <div className="space-y-1">
                                                <p className="text-2xl font-bold">${product.price.toFixed(2)}</p>
                                                {product.rating && (
                                                    <div className="flex items-center gap-1">
                                                        <Star className="h-4 w-4 fill-primary text-primary" />
                                                        <span className="text-sm">{product.rating}</span>
                                                    </div>
                                                )}
                                            </div>
                                            <Button size="icon">
                                                <ShoppingCart className="h-4 w-4" />
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </section>
                </>
            )}

            {/* Popular Products Section */}
            {popularProducts.length > 0 && (
                <>
                    <Separator />
                    <section className="py-16 md:py-24">
                        <div className="container mx-auto px-4">
                            <div className="mb-12 text-center">
                                <h2 className="mb-4 text-3xl font-bold tracking-tight md:text-4xl">
                                    Customer Favorites
                                </h2>
                                <p className="text-lg text-muted-foreground">
                                    Most loved dishes by our customers
                                </p>
                            </div>
                            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {popularProducts.slice(0, 6).map((product) => (
                                    <Card key={product.id} className="group overflow-hidden">
                                        <div className="aspect-video bg-gradient-to-br from-primary/10 to-secondary/10 relative overflow-hidden">
                                            <div className="absolute inset-0 flex items-center justify-center">
                                                <ChefHat className="h-20 w-20 text-primary/40" />
                                            </div>
                                        </div>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <CardTitle>{product.name}</CardTitle>
                                                <Badge variant="secondary">{product.category}</Badge>
                                            </div>
                                            <CardDescription>{product.description}</CardDescription>
                                        </CardHeader>
                                        <CardFooter className="flex items-center justify-between">
                                            <p className="text-2xl font-bold">${product.price.toFixed(2)}</p>
                                            <Button>Add to Cart</Button>
                                        </CardFooter>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </section>
                </>
            )}

            {/* CTA Section */}
            <section className="bg-primary py-16 text-primary-foreground md:py-24">
                <div className="container mx-auto px-4 text-center">
                    <Truck className="mx-auto mb-6 h-16 w-16" />
                    <h2 className="mb-4 text-3xl font-bold tracking-tight md:text-4xl">
                        Ready to Order?
                    </h2>
                    <p className="mx-auto mb-8 max-w-2xl text-lg opacity-90">
                        Join thousands of satisfied customers. Order now and get your first delivery free!
                    </p>
                    <Link href="/menu">
                        <Button size="lg" variant="secondary">
                            Browse Menu
                        </Button>
                    </Link>
                </div>
            </section>
        </DefaultLayout>
    );
}
