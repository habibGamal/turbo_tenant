import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Star, Heart, ShoppingCart, TrendingUp, Flame } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Product {
    id: number;
    name: string;
    nameAr?: string;
    description: string;
    descriptionAr?: string;
    price: number;
    image?: string;
    rating?: number;
    reviewsCount?: number;
    category?: string;
    categoryAr?: string;
    badge?: string;
    badgeAr?: string;
    isNew?: boolean;
    isTrending?: boolean;
}

interface ProductSection {
    id: string;
    title: string;
    titleAr?: string;
    subtitle?: string;
    subtitleAr?: string;
    products: Product[];
    icon?: 'trending' | 'flame' | 'star';
}

interface ProductsSectionProps {
    sections?: ProductSection[];
}

export default function ProductsSection({ sections }: ProductsSectionProps) {
    const { t, i18n } = useTranslation();
    const [addingToCart, setAddingToCart] = useState<{ [key: number]: boolean }>({});

    const handleAddToCart = (e: React.MouseEvent, productId: number) => {
        e.preventDefault();
        e.stopPropagation();

        setAddingToCart(prev => ({ ...prev, [productId]: true }));

        router.post(
            route('cart.store'),
            {
                product_id: productId,
                variant_id: null,
                quantity: '1',
                extras: [],
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setAddingToCart(prev => ({ ...prev, [productId]: false }));
                },
            }
        );
    };

    const defaultSections: ProductSection[] = [
        {
            id: 'trending',
            title: t('trendingNow'),
            titleAr: t('trendingNow'),
            subtitle: t('mostPopularDishesWeek'),
            subtitleAr: t('mostPopularDishesWeek'),
            icon: 'trending',
            products: [
                {
                    id: 1,
                    name: 'Margherita Pizza',
                    nameAr: 'بيتزا مارجريتا',
                    description: 'Fresh mozzarella, tomatoes, basil',
                    descriptionAr: 'موزاريلا طازجة، طماطم، ريحان',
                    price: 12.99,
                    rating: 4.8,
                    reviewsCount: 124,
                    category: 'Pizza',
                    categoryAr: 'بيتزا',
                    badge: 'Popular',
                    badgeAr: 'شائع',
                    isTrending: true,
                },
                {
                    id: 2,
                    name: 'Classic Burger',
                    nameAr: 'برجر كلاسيك',
                    description: 'Angus beef, lettuce, tomato, special sauce',
                    descriptionAr: 'لحم أنجوس، خس، طماطم، صلصة خاصة',
                    price: 9.99,
                    rating: 4.6,
                    reviewsCount: 89,
                    category: 'Burgers',
                    categoryAr: 'برجر',
                    isNew: true,
                },
                {
                    id: 3,
                    name: 'Caesar Salad',
                    nameAr: 'سلطة سيزر',
                    description: 'Crisp romaine, parmesan, croutons',
                    descriptionAr: 'خس روماني مقرمش، بارميزان، خبز محمص',
                    price: 8.99,
                    rating: 4.5,
                    reviewsCount: 56,
                    category: 'Salads',
                    categoryAr: 'سلطات',
                },
                {
                    id: 4,
                    name: 'Chocolate Lava Cake',
                    nameAr: 'كيك الشوكولاتة',
                    description: 'Warm chocolate cake with vanilla ice cream',
                    descriptionAr: 'كعكة شوكولاتة دافئة مع آيس كريم الفانيليا',
                    price: 6.99,
                    rating: 4.9,
                    reviewsCount: 142,
                    category: 'Desserts',
                    categoryAr: 'حلويات',
                    badge: 'Best Seller',
                    badgeAr: 'الأكثر مبيعاً',
                },
            ],
        },
    ];

    const sectionsToUse = sections || defaultSections;

    const getSectionIcon = (iconName?: string) => {
        const iconClass = "h-5 w-5";
        switch (iconName) {
            case 'trending':
                return <TrendingUp className={iconClass} />;
            case 'flame':
                return <Flame className={iconClass} />;
            case 'star':
                return <Star className={iconClass} />;
            default:
                return <Star className={iconClass} />;
        }
    };

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    return (
        <>
            {sectionsToUse.map((section, sectionIndex) => (
                <section
                    key={section.id}
                    className={`py-12 md:py-20 ${sectionIndex % 2 === 0 ? 'bg-background' : 'bg-muted/30'}`}
                >
                    <div className="container mx-auto px-4">
                        {/* Section Header */}
                        <div className="flex flex-col md:flex-row md:items-end md:justify-between mb-10 md:mb-14 gap-4">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <div className="p-2 rounded-lg bg-primary/10">
                                        <div className="text-primary">
                                            {getSectionIcon(section.icon)}
                                        </div>
                                    </div>
                                    <h2 className="text-3xl md:text-4xl font-bold tracking-tight">
                                        {getText(section.title, section.titleAr)}
                                    </h2>
                                </div>
                                {section.subtitle && (
                                    <p className="text-lg text-muted-foreground ltr:md:ml-12 rtl:md:mr-12">
                                        {getText(section.subtitle, section.subtitleAr)}
                                    </p>
                                )}
                            </div>
                            <Link href="/menu">
                                <Button variant="outline" className="gap-2">
                                    {t('View All')}
                                    <span>→</span>
                                </Button>
                            </Link>
                        </div>

                        {/* Products Grid */}
                        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {section.products.map((product) => (
                                <Link key={product.id} href={`/products/${product.id}`}>
                                    <Card
                                        className="group overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
                                    >
                                    {/* Product Image */}
                                    <div className="aspect-square bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden">
                                        {/* Placeholder for image - replace with actual image */}
                                        <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary/10 via-primary/5 to-transparent">
                                            <div className="text-6xl">🍽️</div>
                                        </div>

                                        {/* Badges */}
                                        <div className="absolute top-2 ltr:left-2 rtl:right-2 flex flex-col gap-2">
                                            {product.badge && (
                                                <Badge className="backdrop-blur-sm bg-primary/90 shadow-lg">
                                                    {getText(product.badge, product.badgeAr)}
                                                </Badge>
                                            )}
                                            {product.isNew && (
                                                <Badge variant="secondary" className="backdrop-blur-sm shadow-lg">
                                                    {t('new')}
                                                </Badge>
                                            )}
                                        </div>

                                        {/* Favorite Button */}
                                        <Button
                                            size="icon"
                                            variant="secondary"
                                            className="absolute top-2 ltr:right-2 rtl:left-2 opacity-0 group-hover:opacity-100 transition-all backdrop-blur-sm shadow-lg"
                                        >
                                            <Heart className="h-4 w-4" />
                                        </Button>

                                        {/* Trending Indicator */}
                                        {product.isTrending && (
                                            <div className="absolute bottom-2 ltr:right-2 rtl:left-2">
                                                <div className="flex items-center gap-1 px-2 py-1 rounded-full bg-orange-500/90 text-white text-xs font-semibold backdrop-blur-sm shadow-lg">
                                                    <Flame className="h-3 w-3" />
                                                    {t('hot')}
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Product Info */}
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="space-y-1 flex-1">
                                                <h3 className="font-semibold text-lg leading-tight line-clamp-1">
                                                    {getText(product.name, product.nameAr)}
                                                </h3>
                                                {product.category && (
                                                    <Badge variant="outline" className="text-xs">
                                                        {getText(product.category, product.categoryAr)}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <p className="text-sm text-muted-foreground line-clamp-2">
                                            {getText(product.description, product.descriptionAr)}
                                        </p>
                                    </CardHeader>

                                    {/* Product Footer */}
                                    <CardFooter className="flex items-center justify-between pt-0">
                                        <div className="space-y-1">
                                            <div className="text-2xl font-bold">
                                                ${product.price.toFixed(2)}
                                            </div>
                                            {product.rating && (
                                                <div className="flex items-center gap-1 text-sm">
                                                    <Star className="h-3.5 w-3.5 fill-yellow-400 text-yellow-400" />
                                                    <span className="font-medium">{product.rating}</span>
                                                    {product.reviewsCount && (
                                                        <span className="text-muted-foreground">
                                                            ({product.reviewsCount})
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        <Button
                                            size="icon"
                                            className="shrink-0 shadow-lg"
                                            onClick={(e) => handleAddToCart(e, product.id)}
                                            disabled={addingToCart[product.id]}
                                        >
                                            <ShoppingCart className="h-4 w-4" />
                                        </Button>
                                    </CardFooter>
                                </Card>
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            ))}
        </>
    );
}
