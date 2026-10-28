import React, { useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ImageWithFallback } from '@/components/ui/image';
import { ShoppingCart, Star, Heart, Flame, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { apiClient } from '@/core/api/client';

import { Product } from '@/types';
import ProductOptionsModal from './ProductOptionsModal';
import { useFavoritesStorage } from '@/plugins/customer-favorites';
import { hasActiveDiscount, getEffectivePrice, getDiscountPercent } from '@/lib/pricing';
import { useFormatMoney } from '@/lib/money';
import { useAddToCart } from '@/plugins/cart-engine/api';

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
    const navigate = useNavigate();
    const formatMoney = useFormatMoney();
    const { mutate: addToCartDirect, isPending: isAddingDirect } = useAddToCart();
    const { isFavorite, toggleFavorite } = useFavoritesStorage();
    const isProductActive = product.is_active ?? true;

    const handleLinkClick = (e: React.MouseEvent) => {
        if (!isProductActive) {
            e.preventDefault();
            return;
        }

        if (onClick) {
            e.preventDefault();
            onClick();
        }
    };

    const [isModalOpen, setIsModalOpen] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(false);
    const fullProduct = useRef<Product | null>(null);

    const hasOptions = Boolean(
        (product as any).has_options ||
        (product as any).has_variants ||
        (product as any).has_extras ||
        product.sell_by_weight ||
        (product.variants && product.variants.length > 0) ||
        (product as any).extra_option_id ||
        (product as any).extra_option ||
        (product as any).extraOption ||
        (product as any).weight_option ||
        (product as any).weightOption
    );

    const handleAddToCartClick = async (e: React.MouseEvent) => {
        e.stopPropagation();

        if (!isProductActive) {
            return;
        }

        setIsLoading(true);
        try {
            const response = await apiClient.get<any>(`/api/catalog-products/${product.id}`);
            fullProduct.current = response.data.product || response.data.data;
            setIsModalOpen(true);
        } catch (error) {
            console.error("Failed to fetch product details", error);
            // Fallback to navigation if fetch fails
            navigate(`/products/${product.id}`);
        } finally {
            setIsLoading(false);
        }
        return;
    };

    const handleButtonClick = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!isProductActive || isLoading || addingToCart || isAddingDirect) return;
        if (hasOptions) {
            handleAddToCartClick(e);
        } else if (onAddToCart) {
            onAddToCart(e, product.id);
        } else {
            addToCartDirect(
                { product_id: product.id, quantity: '1' },
                {
                    onError: () => {
                        toast.error(t('failedToAddToCart', 'Failed to add to cart'));
                    },
                }
            );
        }
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

    const hasDiscount = hasActiveDiscount(product);
    const displayPrice = getEffectivePrice(product);
    const discountPercent = getDiscountPercent(product);

    return (
        <>
            <Card
                data-testid="product-card"
                className={`group flex flex-col h-full transition-all duration-300 ${isProductActive ? 'hover:shadow-lg hover:-translate-y-1' : 'opacity-70 grayscale cursor-not-allowed'}`}
                aria-disabled={!isProductActive}
            >
                {/* Product Image wrapped in Link */}
                <div className="relative aspect-square overflow-hidden rounded-t-lg">
                    {isProductActive ? (
                        <Link
                            to={`/products/${product.id}`}
                            onClick={handleLinkClick}
                            className="block w-full h-full rounded-t-lg"
                            tabIndex={-1}
                            aria-hidden="true"
                        >
                            <ImageWithFallback
                                src={product.image}
                                alt={getText(product.name, product.name_ar)}
                                className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                        </Link>
                    ) : (
                        <div className="w-full h-full">
                            <ImageWithFallback
                                src={product.image}
                                alt={getText(product.name, product.name_ar)}
                                className="w-full h-full object-cover"
                            />
                        </div>
                    )}

                    {/* Badges */}
                    <div className="absolute z-10 top-2 start-2 flex flex-col gap-2 pointer-events-none">
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
                        {hasDiscount && (
                            <Badge variant="destructive" className="flex items-center gap-1 shadow-lg">
                                <Flame className="h-3 w-3" />
                                -{discountPercent}%
                            </Badge>
                        )}
                    </div>

                    {/* Favorite Button - NOT nested inside the link */}
                    {showFavorite && (
                        <Button
                            data-testid="product-favorite-toggle"
                            variant="secondary"
                            size="icon"
                            className="absolute top-2 end-2 h-9 w-9 rounded-full bg-card/90 backdrop-blur-sm hover:bg-card shadow-md transition-transform active:scale-95 z-20"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                toggleFavorite(product.id);
                            }}
                            aria-label={
                                isFavorite(product.id)
                                    ? t("removeFromFavorites", "Remove from favorites")
                                    : t("addToFavorites", "Add to favorites")
                            }
                            aria-pressed={isFavorite(product.id)}
                        >
                            <Heart
                                className={`h-5 w-5 transition-colors ${
                                    isFavorite(product.id)
                                        ? 'fill-destructive text-destructive'
                                        : 'text-foreground hover:text-destructive'
                                }`}
                            />
                        </Button>
                    )}
                </div>

                <CardHeader className="p-4 pb-2">
                    {/* Category */}
                    {getCategoryName() && (
                        <p className="text-xs text-muted-foreground mb-1">
                            {getCategoryName()}
                        </p>
                    )}

                    {/* Product Name wrapped in Link */}
                    <h3 data-testid="product-card-title" className="font-semibold text-lg line-clamp-1">
                        {isProductActive ? (
                            <Link
                                to={`/products/${product.id}`}
                                onClick={handleLinkClick}
                                className="group-hover:text-primary transition-colors focus-visible:outline-none focus-visible:underline"
                            >
                                {getText(product.name, product.name_ar)}
                            </Link>
                        ) : (
                            <span className="text-muted-foreground">
                                {getText(product.name, product.name_ar)}
                            </span>
                        )}
                    </h3>

                    {/* Rating */}
                    {product.rating && (
                        <div className="flex items-center gap-1 mt-1">
                            <Star className="h-4 w-4 fill-primary text-primary" />
                            <span className="text-xs font-medium">{product.rating}</span>
                            {product.reviewsCount && (
                                <span className="text-xs text-muted-foreground">
                                    ({product.reviewsCount})
                                </span>
                            )}
                        </div>
                    )}
                </CardHeader>

                <CardContent className="p-4 pt-0 flex-1 flex flex-col justify-between">
                    {/* Description */}
                    <div className="flex-1 mb-4">
                        <p className="text-sm text-muted-foreground line-clamp-2">
                            {getText(product.description, product.descriptionAr)}
                        </p>
                    </div>

                    {/* Price and Add to Cart */}
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <div className="flex items-baseline gap-2">
                                <span data-testid="product-card-price" className="text-xl font-bold text-primary">
                                    {formatMoney(displayPrice)}
                                </span>
                                {hasDiscount && (
                                    <span className="text-xs text-muted-foreground line-through">
                                        {formatMoney(product.base_price)}
                                    </span>
                                )}
                            </div>
                            {product.sell_by_weight && (
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {t('perKg')}
                                </p>
                            )}
                        </div>

                        <Button
                            data-testid="product-add-to-cart"
                            size="sm"
                            className="rounded-full shadow-sm hover:shadow transition-all"
                            onClick={handleButtonClick}
                            disabled={isLoading || addingToCart || isAddingDirect || !isProductActive}
                        >
                            {isLoading || addingToCart || isAddingDirect ? (
                                <Loader2 className="h-4 w-4 animate-spin me-1" />
                            ) : (
                                <ShoppingCart className="h-4 w-4 me-1" />
                            )}
                            {t('addToCart')}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {fullProduct.current && (
                <ProductOptionsModal
                    product={fullProduct.current}
                    isOpen={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                />
            )}
        </>
    );
}
