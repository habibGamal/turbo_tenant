import React, { useRef } from 'react';
import ReactPixel from 'react-facebook-pixel';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ImageWithFallback } from '@/components/ui/image';
import { ShoppingCart, Star, Heart, Flame, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

import { Product } from '@/types';
import ProductOptionsModal from './product/ProductOptionsModal';
import { useFavorites } from '@/hooks/useFavorites';

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
    const { isFavorite, toggleFavorite } = useFavorites();
    const isProductActive = product.is_active ?? true;

    const handleCardClick = () => {
        if (!isProductActive) {
            return;
        }

        if (onClick) {
            onClick();
        } else {
            router.visit(`/products/${product.id}`);
        }
    };

    const [isModalOpen, setIsModalOpen] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(false);
    const fullProduct = useRef<Product | null>(null);

    const handleAddToCartClick = async (e: React.MouseEvent) => {
        e.stopPropagation();

        if (!isProductActive) {
            return;
        }


        setIsLoading(true);
        try {
            const response = await axios.get(`/api/products/${product.id}`);
            fullProduct.current = response.data.product;
            setIsModalOpen(true);
        } catch (error) {
            console.error("Failed to fetch product details", error);
            // Fallback to navigation if fetch fails
            router.visit(`/products/${product.id}`);
        } finally {
            setIsLoading(false);
        }
        return;
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

        return getText(product.category.name, product.category.name_ar);
    };

    const displayPrice = product.price_after_discount || product.base_price;
    const hasDiscount = Number(product.price_after_discount) < Number(product.base_price);

    return (
        <>
            <Card
                className={`group transition-all duration-300 ${isProductActive ? 'hover:shadow-lg hover:-translate-y-1 cursor-pointer' : 'opacity-70 grayscale cursor-not-allowed'}`}
                onClick={handleCardClick}
                aria-disabled={!isProductActive}
            >
                {/* Product Image */}
                <div className="relative aspect-square overflow-hidden rounded-t-lg">
                    <ImageWithFallback
                        src={product.image}
                        alt={getText(product.name, product.name_ar)}
                        className={`w-full h-full object-cover transition-transform duration-300 ${isProductActive ? 'group-hover:scale-105' : ''}`}
                    />

                    {/* Badges */}
                    <div className="absolute z-10 top-2 ltr:left-2 rtl:right-2 flex flex-col gap-2">
                        {!isProductActive && (
                            <Badge variant="secondary" className="bg-muted text-muted-foreground shadow-lg">
                                {t('unavailable')}
                            </Badge>
                        )}
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
                            className={`absolute top-2 ltr:right-2 rtl:left-2 opacity-0 group-hover:opacity-100 transition-all backdrop-blur-sm shadow-lg ${isFavorite(product.id) ? "opacity-100 text-red-500" : ""}`}
                            onClick={(e) => {
                                e.stopPropagation();
                                toggleFavorite(product.id);
                                if (!isFavorite(product.id)) {
                                    ReactPixel.track("AddToWishlist", {
                                        content_ids: [product.id],
                                        contents: [{ id: product.id, quantity: 1 }],
                                        value: product.price_after_discount || product.base_price,
                                        currency: "EGP",
                                    });
                                }
                            }}
                        >
                            <Heart className={`h-4 w-4 ${isFavorite(product.id) ? "fill-current" : ""}`} />
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
                        <h3 className="font-semibold text-lg leading-tight ">
                            {getText(product.name, product.name_ar)}
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
                                    {formatPrice(displayPrice as number)}
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
                                    {product.reviewsCount !== undefined && (
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
                            disabled={!isProductActive || addingToCart || isLoading}
                        >
                            {isLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <ShoppingCart className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                </CardContent>
            </Card>
            {
                fullProduct.current && (
                    <ProductOptionsModal
                        product={fullProduct.current}
                        isOpen={isModalOpen}
                        onClose={() => setIsModalOpen(false)}
                    />
                )
            }
        </>
    );
}
