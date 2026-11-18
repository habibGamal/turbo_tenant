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

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const getCategoryName = () => {
        if (!product.category) return null;
        if (typeof product.category === 'string') {
            return getText(product.category, (product as any).categoryAr);
        }
        return getText(product.category.name, product.category.name_ar);
    };

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
                'group relative flex items-center justify-between gap-3 p-3 rounded-2xl border border-border bg-muted/70 shadow-none dark:bg-black/25 transition-colors text-start select-none',
                isProductActive
                    ? 'hover:border-primary/40 cursor-pointer'
                    : 'opacity-60 grayscale cursor-not-allowed'
            )}
        >
            {/* Small Image (Right in RTL, Left in LTR) */}
            <div className="relative shrink-0 w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden bg-background dark:bg-card border border-border/80">
                <ImageWithFallback
                    src={product.image}
                    alt={productName}
                    className={cn(
                        'w-full h-full object-cover transition-transform duration-300',
                        isProductActive ? 'group-hover:scale-105' : ''
                    )}
                />
            </div>

            {/* Text & Price Info */}
            <div className="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                <div className="space-y-1">
                    {(getCategoryName() || !isProductActive || (hasDiscount && isProductActive)) && (
                        <div className="flex items-center gap-1.5 flex-wrap">
                            {getCategoryName() && (
                                <Badge variant="outline" className="text-xs font-normal border-border/80 bg-background/80 dark:bg-card/60">
                                    {getCategoryName()}
                                </Badge>
                            )}
                            {!isProductActive && (
                                <Badge variant="secondary" className="text-[10px] px-1.5 py-0 h-4 bg-muted text-muted-foreground">
                                    {t('unavailable', 'Unavailable')}
                                </Badge>
                            )}
                            {hasDiscount && isProductActive && (
                                <Badge variant="destructive" className="text-[10px] px-1.5 py-0 h-4 flex items-center gap-0.5">
                                    <Flame className="h-2.5 w-2.5" />
                                    <span dir="ltr">-{discountPercent}%</span>
                                </Badge>
                            )}
                        </div>
                    )}
                    <h3
                        data-testid="menu-item-name"
                        className="font-bold text-sm sm:text-base text-foreground line-clamp-1 group-hover:text-primary transition-colors"
                    >
                        {productName}
                    </h3>
                    {productDescription && (
                        <p dir="auto" className="text-xs text-muted-foreground line-clamp-2">
                            {productDescription}
                        </p>
                    )}
                </div>

                <div className="flex items-center justify-between gap-2 mt-2 rtl:flex-row ltr:flex-row-reverse">
                    <div className="flex items-baseline gap-2">
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

                    <Button
                        type="button"
                        size="icon"
                        aria-label={hasOptions ? t('customizeProduct', 'Customize') : t('addToCart', 'Add to Cart')}
                        data-testid={`menu-item-add-${product.id}`}
                        disabled={!isProductActive || isPending}
                        onClick={handlePlusClick}
                        className="h-11 w-11 md:h-11 md:w-11 rounded-full shadow-none bg-primary hover:bg-primary/90 text-primary-foreground flex items-center justify-center transition-transform active:scale-90 z-10 shrink-0 p-0"
                    >
                        {isPending ? (
                            <LoaderIcon className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                            <Plus className="h-4 w-4" />
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
