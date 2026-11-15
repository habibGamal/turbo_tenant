/**
 * TypeScript Interfaces for CartEngine Plugin
 */

declare global {
    function parseFloat(string: string | number): number;
}

export interface CartItemExtra {
    id: number;
    name: string;
    name_ar?: string;
    price: number;
    quantity: number;
}

export interface CartWeightOptionValue {
    id: number;
    value: string;
    label?: string;
}

export interface CartProduct {
    id: number | null;
    name: string;
    name_ar?: string;
    image?: string;
    sell_by_weight?: boolean;
    weight_option?: any;
}

export interface CartVariant {
    id: number;
    name: string;
    name_ar?: string;
}

export interface CartLineComponentExtra {
    id: number;
    name: string;
    name_ar?: string | null;
    price: number;
    quantity: number;
}

export interface CartLineComponent {
    source_id?: number | null;
    group_name: string;
    group_name_ar?: string | null;
    product_id: number | null;
    variant_id: number | null;
    product_name: string;
    product_name_ar?: string | null;
    variant_name?: string | null;
    variant_name_ar?: string | null;
    quantity: number | string;
    unit_price_adjustment: number;
    sort_order?: number;
    extras?: CartLineComponentExtra[];
    notes?: string | null;
}

export interface CartItem {
    id: number | string;
    type?: 'product' | 'package';
    reference_id?: number | null;
    notes?: string | null;
    components?: CartLineComponent[];
    product_id: number | null;
    quantity: number;
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

export interface PackageCartLineSelection {
    package_item_id: number;
    quantity: number;
    variant_id?: number | null;
    extras?: Array<{ id: number; quantity: number }>;
    notes?: string | null;
}

export interface AddPackageToCartPayload {
    type: 'package';
    package_id: number;
    quantity: number;
    notes?: string | null;
    selections: PackageCartLineSelection[];
}

export interface AddToCartPayload {
    product_id?: number | null;
    type?: 'product' | 'package';
    package_id?: number;
    selections?: PackageCartLineSelection[];
    notes?: string | null;
    variant_id?: number | null;
    weight_option_value_id?: number | null;
    quantity?: number | string;
    weight_multiplier?: number;
    extras?: Array<{ id: number; quantity?: number }>;
}

export interface UpdateCartItemPayload {
    quantity?: number | string;
    weight_multiplier?: number;
    weight_option_value_id?: number | null;
}

export type CartApiResponse = import('@/core/api/types').ApiSuccess<Cart>;
