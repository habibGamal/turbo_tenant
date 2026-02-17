import React from 'react';
import ReactPixel from 'react-facebook-pixel';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Product } from '@/types';
import { useProductOptions } from '@/hooks/useProductOptions';
import ProductOptions from './ProductOptions';
import ProductInfo from './ProductInfo';
import { ImageWithFallback } from '@/components/ui/image';
import { useTranslation } from 'react-i18next';

interface ProductOptionsModalProps {
    product: Product;
    isOpen: boolean;
    onClose: () => void;
}

export default function ProductOptionsModal({
    product,
    isOpen,
    onClose,
}: ProductOptionsModalProps) {
    const { t, i18n } = useTranslation();
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
    } = useProductOptions({ product });

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const handleAddToCartWrapper = () => {
        handleAddToCart(() => {
            let price = product.price_after_discount || product.base_price || product.price;
            let contentId = product.id;
            ReactPixel.track("AddToCart", {
                content_ids: [contentId],
                content_type: "product",
                contents: [{ id: contentId, quantity: quantity }],
                value: price,
                currency: "EGP",
            });
            onClose();
        });
    };

    // Mock favorite functionality for now as it's not part of the modal requirements but needed for ProductOptions
    const isFavorite = false;
    const handleToggleFavorite = () => { };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-lg md:max-w-2xl lg:max-w-5xl max-h-[90vh] overflow-y-auto">
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
                                {getText(product.description, product.descriptionAr)}
                            </p>
                        </div>
                        <div className="text-2xl font-bold text-primary">
                            {calculateTotalPrice().toFixed(2)} {t('currency')}
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
                            isAddingToCart={isAddingToCart}
                            isFavorite={isFavorite}
                            handleToggleFavorite={handleToggleFavorite}
                        />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
