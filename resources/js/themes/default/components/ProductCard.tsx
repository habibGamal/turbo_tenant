import React from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ShoppingCart, Star, Heart, Flame } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Product {
    id: number;
    name: string;
    nameAr?: string;
    description: string;
    descriptionAr?: string;
    price: number;
    base_price?: number;
    price_after_discount?: number;
    image?: string;
    rating?: number;
    reviewsCount?: number;
    category?: {
        id: number;
        name: string;
        nameAr?: string;
    } | string;
    categoryAr?: string;
    badge?: string;
    badgeAr?: string;
    isNew?: boolean;
    isTrending?: boolean;
    sell_by_weight?: boolean;
}

interface ProductCardProps {
    product: Product;
    onAddToCart?: (e: React.MouseEvent, productId: number) => void;
    addingToCart?: boolean;
    showFavorite?: boolean;
    onClick?: () => void;
}

export default function ProductCard({
    product,
    onAddToCart,
    addingToCart = false,
    showFavorite = false,
    onClick,
}: ProductCardProps) {
    const { t, i18n } = useTranslation();

    const handleCardClick = () => {
        if (onClick) {
            onClick();
        } else {
            router.visit(`/products/${product.id}`);
        }
    };

    const handleAddToCartClick = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (onAddToCart) {
            onAddToCart(e, product.id);
        } else {
            router.visit(`/products/${product.id}`);
        }
    };

    const formatPrice = (price: number) => {
        return `${Number(price).toFixed(2)} ${t("currency")}`;
    };

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const getCategoryName = () => {
        if (!product.category) return null;

        if (typeof product.category === 'string') {
            return getText(product.category, product.categoryAr);
        }

        return getText(product.category.name, product.category.nameAr);
    };

    const displayPrice = product.price_after_discount || product.price;
    const hasDiscount = !!product.price_after_discount && product.base_price;

    return (
        <Card
            className="group hover:shadow-lg transition-all duration-300 hover:-translate-y-1 cursor-pointer"
            onClick={handleCardClick}
        >
            {/* Product Image */}
            <div className="relative aspect-square overflow-hidden rounded-t-lg">
                {product.image ? (
                    <img
                        src={product.image}
                        alt={getText(product.name, product.nameAr)}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                ) : (
                    <div className="w-full h-full bg-gradient-to-br from-primary/10 via-primary/5 to-transparent flex items-center justify-center">
                        <div className="text-6xl">🍽️</div>
                    </div>
                )}

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
                    {hasDiscount && !product.badge && (
                        <Badge className="backdrop-blur-sm bg-red-500 shadow-lg">
                            {t('sale')}
                        </Badge>
                    )}
                </div>

                {/* Favorite Button */}
                {showFavorite && (
                    <Button
                        size="icon"
                        variant="secondary"
                        className="absolute top-2 ltr:right-2 rtl:left-2 opacity-0 group-hover:opacity-100 transition-all backdrop-blur-sm shadow-lg"
                        onClick={(e) => {
                            e.stopPropagation();
                            // Handle favorite logic here
                        }}
                    >
                        <Heart className="h-4 w-4" />
                    </Button>
                )}

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
                <div className="space-y-1">
                    <h3 className="font-semibold text-lg leading-tight line-clamp-1">
                        {getText(product.name, product.nameAr)}
                    </h3>
                    {getCategoryName() && (
                        <Badge variant="outline" className="text-xs">
                            {getCategoryName()}
                        </Badge>
                    )}
                </div>
                <p className="text-sm text-muted-foreground line-clamp-2 mt-2">
                    {getText(product.description, product.descriptionAr)}
                </p>
            </CardHeader>

            {/* Product Footer */}
            <CardContent className="pt-0">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="text-xl font-bold text-primary">
                                {formatPrice(displayPrice)}
                            </span>
                            {hasDiscount && (
                                <span className="text-sm text-muted-foreground line-through">
                                    {formatPrice(product.base_price!)}
                                </span>
                            )}
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
                        onClick={handleAddToCartClick}
                        disabled={addingToCart}
                    >
                        <ShoppingCart className="h-4 w-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
