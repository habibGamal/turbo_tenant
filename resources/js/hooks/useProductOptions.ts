import { useState } from 'react';
import { Product } from '@/types';
import { addToCart, ExtraWithQuantity } from '@/utils/cartUtils';

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

    // Calculate total price
    const calculateTotalPrice = () => {
        let basePrice = product.price_after_discount ?? product.base_price ?? 0;

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

    const handleExtraToggle = (extraId: number) => {
        if (!product.extraOption) return;

        const newExtras = new Map(selectedExtras);

        if (newExtras.has(extraId)) {
            newExtras.delete(extraId);
        } else {
            // Check if max selections is reached
            if (product.extraOption.max_selections && newExtras.size >= product.extraOption.max_selections) {
                // If not allow_multiple, replace the existing selection
                if (!product.extraOption.allow_multiple) {
                    newExtras.clear();
                    newExtras.set(extraId, 1);
                }
                return;
            }
            newExtras.set(extraId, 1);
        }

        setSelectedExtras(newExtras);
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
    };
}
