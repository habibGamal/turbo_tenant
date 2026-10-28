/**
 * TypeScript Interfaces for CartEngine Plugin
 */

export interface CartItemExtra {
    id: number;
    name: string;
    name_ar?: string;
    price: number;
    quantity: number;
}

export interface CartWeightOption {
    id: number;
    name: string;
    name_ar?: string;
    unit?: string;
    step?: number;
    min_weight?: number;
    max_weight?: number;
}

export interface CartWeightOptionValue {
    id: number;
    value: string;
    label?: string;
    price_multiplier?: number;
}

export interface CartProduct {
    id: number;
    name: string;
    name_ar?: string;
    image?: string;
    base_price: number;
    price_after_discount?: number;
    sell_by_weight?: boolean;
    weight_option?: CartWeightOption;
}

export interface CartVariant {
    id: number;
    name: string;
    name_ar?: string;
    price: number | null;
}

export interface CartItem {
    id: number | string;
    product_id: number;
    variant_id?: number | null;
    weight_option_value_id?: number | null;
    quantity: string;
    weight_multiplier: number;
    product: CartProduct;
    variant?: CartVariant | null;
    weight_option_value?: CartWeightOptionValue | null;
    extras: CartItemExtra[];
    price: number;
    extras_total: number;
    subtotal: number;
}

export interface Cart {
    items: CartItem[];
    total: number;
}

export interface AddToCartPayload {
    product_id: number;
    variant_id?: number | null;
    weight_option_value_id?: number | null;
    quantity?: string;
    weight_multiplier?: number;
    extras?: Array<{ id: number; quantity?: number }>;
}

export interface UpdateCartItemPayload {
    quantity?: string;
    weight_multiplier?: number;
    weight_option_value_id?: number | null;
}

export interface CartApiResponse {
    status: string;
    cart: Cart;
    message?: string;
}
