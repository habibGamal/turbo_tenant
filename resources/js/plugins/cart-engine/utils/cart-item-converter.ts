import { CommerceItem } from '@/core/analytics';
import { CartItem } from '../types';

export interface CartItemConversionOverrides {
    quantity?: number | string;
    weight_multiplier?: number | string;
    variant_id?: number | string | null;
}

/**
 * Converts a cart line item to a vendor-neutral CommerceItem for analytics tracking.
 *
 * Rules:
 * - Sell-by-weight products: unit price = subtotal / weight_multiplier.
 * - Standard products: unit price = base price + extras_total (Number(price) + Number(extras_total)).
 * - Allows overriding quantity, weight multiplier, or variant_id (used by AddToCart variables).
 */
export function cartItemToCommerceItem(
    item: CartItem,
    overrides?: CartItemConversionOverrides
): CommerceItem {
    const isSellByWeight = Boolean(item.product?.sell_by_weight);
    let quantity = 1;
    let itemPrice = 0;

    if (isSellByWeight) {
        const multiplier = Number(item.weight_multiplier) || 1;
        quantity = overrides?.weight_multiplier
            ? Number(overrides.weight_multiplier)
            : (overrides?.quantity ? Number(overrides.quantity) : multiplier);
        itemPrice = multiplier > 0 ? (Number(item.subtotal) || 0) / multiplier : (Number(item.subtotal) || 0);
    } else {
        quantity = overrides?.quantity
            ? Number(overrides.quantity)
            : (Number(item.quantity) || 1);
        const basePrice = Number(item.price) || 0;
        const extrasTotal = Number(item.extras_total) || 0;
        itemPrice = basePrice + extrasTotal;
    }

    const variantId = overrides?.variant_id !== undefined
        ? overrides.variant_id
        : (item.variant?.id ?? null);

    return {
        product_id: item.product_id ?? item.reference_id ?? 0,
        variant_id: variantId,
        quantity,
        item_price: itemPrice,
        name: item.product?.name,
    };
}
