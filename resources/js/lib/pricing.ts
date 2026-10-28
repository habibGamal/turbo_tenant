export interface PriceableProduct {
    base_price?: number | string | null;
    price_after_discount?: number | string | null;
    price?: number | string | null;
}

export function hasActiveDiscount(product?: PriceableProduct | null): boolean {
    if (!product) return false;

    const discountPrice =
        product.price_after_discount !== null && product.price_after_discount !== undefined
            ? Number(product.price_after_discount)
            : NaN;
    const basePrice =
        product.base_price !== null && product.base_price !== undefined
            ? Number(product.base_price)
            : NaN;

    return (
        !Number.isNaN(discountPrice) &&
        !Number.isNaN(basePrice) &&
        discountPrice > 0 &&
        basePrice > 0 &&
        discountPrice < basePrice
    );
}

export function getEffectivePrice(product?: PriceableProduct | null): number {
    if (!product) return 0;

    if (hasActiveDiscount(product)) {
        return Number(product.price_after_discount);
    }

    const fallback = product.base_price ?? product.price ?? 0;
    const num = Number(fallback);
    return Number.isNaN(num) ? 0 : num;
}

export function getDiscountPercent(product?: PriceableProduct | null): number {
    if (!product || !hasActiveDiscount(product)) return 0;

    const basePrice = Number(product.base_price);
    const discountPrice = Number(product.price_after_discount);

    if (basePrice <= 0) return 0;

    return Math.round(((basePrice - discountPrice) / basePrice) * 100);
}
