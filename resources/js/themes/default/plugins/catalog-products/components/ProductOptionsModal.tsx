import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Product } from '@/types';
import { NormalizedProduct } from '@/plugins/catalog-products/types';
import { useProductOptions } from '@/hooks/useProductOptions';
import { useFavorites } from '@/hooks/useFavorites';
import ProductOptions from './ProductOptions';
import ProductInfo from './ProductInfo';
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
            <DialogContent data-testid="product-options-modal" className="sm:max-w-lg md:max-w-2xl lg:max-w-5xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('customizeProduct')}</DialogTitle>
                </DialogHeader>

                <div className="grid lg:grid-cols-2 gap-6">
                    {/* Product Summary */}
                    <div className="space-y-4">
                        <div className="aspect-square rounded-lg overflow-hidden border">
                            <ImageWithFallback
                                src={product.image}
                                alt={getText(product.name, product.name_ar)}
                                className="w-full h-full object-cover"
                            />
                        </div>
                    </div>

                    {/* Options */}
                    <div>
                        <div>
                            <h3 className="font-bold text-lg">
                                {getText(product.name, product.name_ar)}
                            </h3>
                            <p className="text-sm text-muted-foreground line-clamp-2">
                                {getText(product.description || '', product.descriptionAr)}
                            </p>
                        </div>
                        <div className="text-2xl font-bold text-primary">
                            {formatMoney(calculateTotalPrice())}
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
                        />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
