import { useState } from 'react';
import { Product } from '@/types';
import { addToCart, ExtraWithQuantity } from '@/utils/cartUtils';
import { getEffectivePrice } from '@/lib/pricing';

interface UseProductOptionsProps {
    product: Product;
}

export function useProductOptions({ product }: UseProductOptionsProps) {
    // For weight-based products, use first weight option value or default, otherwise use 1
    const defaultQuantity = product.sell_by_weight && product.weight_option?.values?.[0]
        ? parseFloat(product.weight_option.values[0].value)
        : 1;

    const [quantity, setQuantity] = useState(defaultQuantity);
    const [selectedWeightValue, setSelectedWeightValue] = useState<number | null>(
        product.weight_option?.values?.[0]?.id || null
    );
    const [selectedVariant, setSelectedVariant] = useState<number | null>(
        product.variants?.[0]?.id || null
    );
    const [selectedExtras, setSelectedExtras] = useState<Map<number, number>>(
        new Map(
            product.extraOption?.items
                .filter((item) => item.is_default)
                .map((item) => [item.id, 1]) || []
        )
    );
    const [isAddingToCart, setIsAddingToCart] = useState(false);
    const [limitReachedFor, setLimitReachedFor] = useState<number | null>(null);

    const minSelections = product.extraOption?.min_selections ?? 0;
    const missingRequiredSelections = Boolean(
        product.extraOption &&
        minSelections > 0 &&
        selectedExtras.size < minSelections
    );
    const canAddToCart = (product.is_active ?? true) && !missingRequiredSelections;

    // Calculate total price
    const calculateTotalPrice = () => {
        let basePrice = getEffectivePrice(product);

        // Add variant price if selected
        if (selectedVariant && product.variants) {
            const variant = product.variants.find(
                (v) => v.id === selectedVariant
            );
            if (variant) {
                basePrice = variant.price ?? basePrice;
            }
        }

        // Calculate extras total
        let extrasTotal = 0;
        if (product.extraOption?.items) {
            selectedExtras.forEach((qty, extraId) => {
                const extra = product.extraOption!.items.find(
                    (item) => item.id === extraId
                );
                if (extra) {
                    extrasTotal += extra.price * qty;
                }
            });
        }

        // For weight-based products: (price * quantity) + (extrasTotal * 1)
        // For regular products: (price + extrasTotal) * quantity
        if (product.sell_by_weight) {
            return (basePrice * quantity) + extrasTotal;
        } else {
            return (basePrice + extrasTotal) * quantity;
        }
    };

    const handleQuantityChange = (delta: number) => {
        if (product.sell_by_weight && product.weight_option?.values) {
            // For weight-based products with discrete values
            const currentIndex = product.weight_option.values.findIndex(
                (v) => v.id === selectedWeightValue
            );
            const newIndex = currentIndex + delta;
            if (newIndex >= 0 && newIndex < product.weight_option.values.length) {
                const newValue = product.weight_option.values[newIndex];
                setSelectedWeightValue(newValue.id);
                setQuantity(parseFloat(newValue.value));
            }
        } else {
            // For regular products, increment/decrement by 1
            const newQty = quantity + delta;
            setQuantity(Math.max(1, newQty));
        }
    };

    const handleExtraToggle = (extraId: number): boolean => {
        if (!product.extraOption) return false;

        const newExtras = new Map(selectedExtras);

        if (newExtras.has(extraId)) {
            newExtras.delete(extraId);
            setLimitReachedFor(null);
            setSelectedExtras(newExtras);
            return true;
        } else {
            const max = product.extraOption.max_selections;
            const isSingleChoice = !product.extraOption.allow_multiple;
            if ((max && newExtras.size >= max) || (isSingleChoice && newExtras.size >= 1)) {
                if (isSingleChoice) {
                    newExtras.clear();
                    newExtras.set(extraId, 1);
                    setSelectedExtras(newExtras);
                    setLimitReachedFor(null);
                    return true;
                }
                setLimitReachedFor(product.extraOption.id);
                return false;
            }

            if (isSingleChoice) {
                newExtras.clear();
            }
            newExtras.set(extraId, 1);
            setLimitReachedFor(null);
            setSelectedExtras(newExtras);
            return true;
        }
    };

    const handleExtraQuantityChange = (extraId: number, delta: number) => {
        const extraItem = product.extraOption?.items.find(item => item.id === extraId);
        if (!extraItem || !extraItem.allow_quantity) return;

        const newExtras = new Map(selectedExtras);
        const currentQty = newExtras.get(extraId) || 0;
        const newQty = Math.max(1, currentQty + delta);

        newExtras.set(extraId, newQty);
        setSelectedExtras(newExtras);
    };

    const handleAddToCart = async (onSuccess?: () => void) => {
        if (!(product.is_active ?? true) || missingRequiredSelections) {
            return;
        }

        setIsAddingToCart(true);

        const extrasArray: ExtraWithQuantity[] = Array.from(selectedExtras.entries()).map(
            ([id, quantity]) => ({ id, quantity })
        );

        await addToCart(
            {
                product_id: product.id,
                variant_id: selectedVariant,
                weight_option_value_id: selectedWeightValue,
                quantity: quantity.toString(),
                extras: extrasArray,
            },
            {
                onSuccess: () => {
                    // Reset form to default state
                    const defaultQty = product.sell_by_weight && product.weight_option?.values?.[0]
                        ? parseFloat(product.weight_option.values[0].value)
                        : 1;
                    setQuantity(defaultQty);
                    setSelectedWeightValue(product.weight_option?.values?.[0]?.id || null);
                    setSelectedExtras(
                        new Map(
                            product.extraOption?.items
                                .filter((item) => item.is_default)
                                .map((item) => [item.id, 1]) || []
                        )
                    );
                    setLimitReachedFor(null);
                    if (onSuccess) onSuccess();
                },
                onFinally: () => {
                    setIsAddingToCart(false);
                },
            }
        );
    };

    return {
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
    };
}
