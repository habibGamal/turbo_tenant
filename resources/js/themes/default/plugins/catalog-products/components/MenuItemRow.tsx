import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ImageWithFallback } from '@/components/ui/image';
import { Plus, LoaderIcon, Flame } from 'lucide-react';
import { toast } from 'sonner';
import { ProductSummary } from '@/plugins/catalog-products';
import { useAddToCart } from '@/plugins/cart-engine/api';
import { hasActiveDiscount, getEffectivePrice, getDiscountPercent } from '@/lib/pricing';
import { useFormatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export function hasProductOptions(product: ProductSummary | any): boolean {
    return Boolean(
        product.has_options ||
        product.has_variants ||
        product.has_extras ||
        product.sell_by_weight ||
        (product.variants && product.variants.length > 0) ||
        product.extra_option_id ||
        product.extra_option ||
        product.weight_option
    );
}

export interface MenuItemRowProps {
    product: ProductSummary;
    onOpenOptions: (product: ProductSummary) => void;
    formatPrice?: (price: number) => string;
}

export default function MenuItemRow({
    product,
    onOpenOptions,
    formatPrice,
}: MenuItemRowProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === 'ar';
    const navigate = useNavigate();
    const { mutate: addToCart, isPending } = useAddToCart();

    const isProductActive = (product.is_active ?? true) && (product as any).is_available !== false;
    const hasOptions = hasProductOptions(product);

    const productName = isRTL && product.name_ar ? product.name_ar : product.name;
    const productDescription = isRTL && (product as any).descriptionAr
        ? (product as any).descriptionAr
        : product.description;

    const hasDiscount = hasActiveDiscount(product);
    const effectivePrice = getEffectivePrice(product);
    const discountPercent = getDiscountPercent(product);

    const formatMoney = useFormatMoney();

    const formatCurrency = (val: number) => {
        if (formatPrice) return formatPrice(val);
        return formatMoney(val);
    };

    const handleRowClick = () => {
        if (!isProductActive) return;
        navigate(`/products/${product.id}`);
    };

    const handlePlusClick = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!isProductActive || isPending) return;

        if (hasOptions) {
            onOpenOptions(product);
        } else {
            // Success toast comes from useAddToCart itself
            addToCart(
                { product_id: product.id, quantity: '1' },
                {
                    onError: () => {
                        toast.error(t('failedToAddToCart', 'Failed to add to cart'));
                    },
                }
            );
        }
    };

    return (
        <div
            data-testid="menu-item-row"
            data-product-id={product.id}
            role="button"
            tabIndex={isProductActive ? 0 : -1}
            aria-disabled={!isProductActive}
            onClick={handleRowClick}
            onKeyDown={(e) => {
                if ((e.key === 'Enter' || e.key === ' ') && isProductActive) {
                    e.preventDefault();
                    handleRowClick();
                }
            }}
            className={cn(
                'group relative flex items-center justify-between gap-3 p-3 rounded-2xl border border-border/50 bg-card transition-all text-start select-none',
                isProductActive
                    ? 'hover:border-primary/40 hover:shadow-sm cursor-pointer'
                    : 'opacity-60 grayscale cursor-not-allowed'
            )}
        >
            {/* Text & Price Info */}
            <div className="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                <div className="space-y-1">
                    <div className="flex items-center gap-1.5 flex-wrap">
                        {!isProductActive && (
                            <Badge variant="secondary" className="text-[10px] px-1.5 py-0 h-4 bg-muted text-muted-foreground">
                                {t('unavailable', 'Unavailable')}
                            </Badge>
                        )}
                        {hasDiscount && isProductActive && (
                            <Badge variant="destructive" className="text-[10px] px-1.5 py-0 h-4 flex items-center gap-0.5">
                                <Flame className="h-2.5 w-2.5" />
                                -{discountPercent}%
                            </Badge>
                        )}
                    </div>
                    <h3
                        data-testid="menu-item-name"
                        className="font-bold text-sm sm:text-base text-foreground line-clamp-1 group-hover:text-primary transition-colors"
                    >
                        {productName}
                    </h3>
                    {productDescription && (
                        <p className="text-xs text-muted-foreground line-clamp-2">
                            {productDescription}
                        </p>
                    )}
                </div>

                <div className="flex items-baseline gap-2 mt-2">
                    <span data-testid="menu-item-price" className="font-bold text-sm sm:text-base text-primary">
                        {formatCurrency(effectivePrice)}
                    </span>
                    {hasDiscount && isProductActive && (
                        <span className="text-xs text-muted-foreground line-through">
                            {formatCurrency(Number(product.base_price))}
                        </span>
                    )}
                    {product.sell_by_weight && (
                        <span className="text-[11px] text-muted-foreground">
                            {t('perKg', 'per kg')}
                        </span>
                    )}
                </div>
            </div>

            {/* Small Image & Plus Button */}
            <div className="relative shrink-0 w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden bg-muted border border-border/40">
                <ImageWithFallback
                    src={product.image}
                    alt={productName}
                    className={cn(
                        'w-full h-full object-cover transition-transform duration-300',
                        isProductActive ? 'group-hover:scale-105' : ''
                    )}
                />

                <Button
                    type="button"
                    size="icon"
                    aria-label={hasOptions ? t('customizeProduct', 'Customize') : t('addToCart', 'Add to Cart')}
                    data-testid={`menu-item-add-${product.id}`}
                    disabled={!isProductActive || isPending}
                    onClick={handlePlusClick}
                    className="absolute bottom-1.5 end-1.5 h-7 w-7 sm:h-8 sm:w-8 rounded-full shadow-md bg-primary hover:bg-primary/90 text-primary-foreground flex items-center justify-center transition-transform active:scale-90 z-10"
                >
                    {isPending ? (
                        <LoaderIcon className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                        <Plus className="h-4 w-4" />
                    )}
                </Button>
            </div>
        </div>
    );
}
