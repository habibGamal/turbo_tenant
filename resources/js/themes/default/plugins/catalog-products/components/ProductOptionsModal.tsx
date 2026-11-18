import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Minus, Plus, ShoppingCart, LoaderIcon } from 'lucide-react';
import { Product } from '@/types';
import { NormalizedProduct } from '@/plugins/catalog-products/types';
import { useProductOptions } from '@/hooks/useProductOptions';
import { useFavorites } from '@/hooks/useFavorites';
import ProductOptions from './ProductOptions';
import { ImageWithFallback } from '@/components/ui/image';
import { useTranslation } from 'react-i18next';
import { getEffectivePrice } from '@/lib/pricing';
import { useFormatMoney, useCurrency } from '@/lib/money';

interface ProductOptionsModalProps {
    product: NormalizedProduct | Product;
    isOpen: boolean;
    onClose: () => void;
}

export default function ProductOptionsModal({
    product,
    isOpen,
    onClose,
}: ProductOptionsModalProps) {
    const { t, i18n } = useTranslation();
    const formatMoney = useFormatMoney();
    const currency = useCurrency();
    const {
        quantity,
        setQuantity,
        selectedWeightValue,
        setSelectedWeightValue,
        selectedVariant,
        setSelectedVariant,
        selectedExtras,
        handleExtraToggle,
        handleExtraQuantityChange,
        handleQuantityChange,
        calculateTotalPrice,
        handleAddToCart,
        isAddingToCart,
        limitReachedFor,
        canAddToCart,
        missingRequiredSelections,
    } = useProductOptions({ product });

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const handleAddToCartWrapper = () => {
        if (!(product.is_active ?? true) || !canAddToCart) {
            return;
        }

        handleAddToCart(() => {
            onClose();
        });
    };

    const { isFavorite, toggleFavorite } = useFavorites();

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent
                data-testid="product-options-modal"
                className="w-[95vw] sm:w-full sm:max-w-lg md:max-w-2xl lg:max-w-4xl max-h-[92vh] sm:max-h-[88vh] flex flex-col p-0 overflow-hidden z-[100] rounded-2xl border bg-background shadow-2xl"
            >
                {/* Fixed Modal Header */}
                <DialogHeader className="px-4 py-3 sm:px-6 sm:py-4 border-b shrink-0 flex flex-row items-center justify-between text-start">
                    <DialogTitle className="text-base sm:text-lg font-bold">
                        {t('customizeProduct')}
                    </DialogTitle>
                </DialogHeader>

                {/* Scrollable Modal Body */}
                <div className="flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6 space-y-4 sm:space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        {/* Product Summary / Image */}
                        <div className="space-y-4">
                            <div className="aspect-square max-h-36 sm:max-h-56 lg:max-h-72 w-full mx-auto rounded-xl overflow-hidden border bg-muted/10 flex items-center justify-center">
                                <ImageWithFallback
                                    src={product.image}
                                    alt={getText(product.name, product.name_ar)}
                                    className="w-full h-full object-contain sm:object-cover"
                                />
                            </div>
                        </div>

                        {/* Options */}
                        <div className="space-y-3 sm:space-y-4">
                            <div>
                                <h3 className="font-bold text-base sm:text-xl text-foreground">
                                    {getText(product.name, product.name_ar)}
                                </h3>
                                {product.description && (
                                    <p className="text-xs sm:text-sm text-muted-foreground line-clamp-2 mt-1">
                                        {getText(product.description || '', product.descriptionAr || (product as any).description_ar)}
                                    </p>
                                )}
                                <div className="text-xl sm:text-2xl font-bold text-primary mt-2">
                                    {formatMoney(calculateTotalPrice())}
                                </div>
                            </div>

                            <ProductOptions
                                product={product}
                                selectedVariant={selectedVariant}
                                setSelectedVariant={setSelectedVariant}
                                selectedExtras={selectedExtras}
                                handleExtraToggle={handleExtraToggle}
                                handleExtraQuantityChange={handleExtraQuantityChange}
                                quantity={quantity}
                                handleQuantityChange={handleQuantityChange}
                                selectedWeightValue={selectedWeightValue}
                                setSelectedWeightValue={setSelectedWeightValue}
                                setQuantity={setQuantity}
                                handleAddToCart={handleAddToCartWrapper}
                                isAddingToCart={isAddingToCart || !(product.is_active ?? true)}
                                isFavorite={isFavorite(product.id)}
                                handleToggleFavorite={() => toggleFavorite(product.id, getEffectivePrice(product))}
                                canAddToCart={canAddToCart}
                                missingRequiredSelections={missingRequiredSelections}
                                limitReachedFor={limitReachedFor}
                                totalPrice={calculateTotalPrice()}
                                hideActions
                            />
                        </div>
                    </div>
                </div>

                {/* Sticky Modal Footer: Quantity & Add to Cart */}
                <div className="p-3 sm:p-4 border-t bg-background/95 backdrop-blur shrink-0 shadow-lg">
                    <div className="flex items-center gap-2 sm:gap-3">
                        {/* Stepper Quantity (if not sell by weight) */}
                        {!product.sell_by_weight && (
                            <div className="flex h-11 sm:h-12 items-center bg-muted/50 border border-border rounded-xl px-1 shrink-0">
                                <Button
                                    type="button"
                                    data-testid="product-quantity-decrement"
                                    variant="ghost"
                                    size="icon"
                                    className="h-9 w-9 sm:h-10 sm:w-10 md:h-10 md:w-10 rounded-lg hover:bg-background shadow-none"
                                    onClick={() => handleQuantityChange(-1)}
                                    disabled={quantity <= 1}
                                >
                                    <Minus className="h-4 w-4" />
                                </Button>
                                <div
                                    data-testid="product-quantity-value"
                                    className="px-2.5 sm:px-3 text-center min-w-[32px] sm:min-w-[40px] text-sm sm:text-base font-bold leading-none"
                                >
                                    {quantity}
                                </div>
                                <Button
                                    type="button"
                                    data-testid="product-quantity-increment"
                                    variant="ghost"
                                    size="icon"
                                    className="h-9 w-9 sm:h-10 sm:w-10 md:h-10 md:w-10 rounded-lg hover:bg-background shadow-none"
                                    onClick={() => handleQuantityChange(1)}
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>
                        )}

                        {/* Add to Cart Button */}
                        <Button
                            type="button"
                            size="lg"
                            data-testid="product-detail-add-to-cart"
                            className="flex-1 h-11 sm:h-12 md:h-12 px-4 sm:px-6 text-sm sm:text-base gap-2 rounded-xl shadow-none font-semibold transition-colors"
                            onClick={handleAddToCartWrapper}
                            disabled={isAddingToCart || !canAddToCart || !(product.is_active ?? true)}
                        >
                            <ShoppingCart className="h-4 w-4 sm:h-5 sm:w-5" />
                            {isAddingToCart ? (
                                <>
                                    {t("adding")}
                                    <LoaderIcon className="h-4 w-4 animate-spin" />
                                </>
                            ) : (
                                `${t("addToCart")} · ${formatMoney(calculateTotalPrice())}`
                            )}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
