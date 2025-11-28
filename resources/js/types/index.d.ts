export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface Category {
    id: number;
    name: string;
    nameAr?: string;
    description?: string;
    image?: string;
}

export interface ProductVariant {
    id: number;
    product_id: number;
    name: string;
    price: number | null;
    is_available: boolean;
    sort_order: number;
}

export interface ExtraOptionItem {
    id: number;
    extra_option_id: number;
    name: string;
    price: number;
    is_default: boolean;
    sort_order: number;
    pos_mapping_type: 'pos_item' | 'notes';
    allow_quantity: boolean;
}

export interface ExtraOption {
    id: number;
    name: string;
    description?: string;
    is_active: boolean;
    min_selections: number;
    max_selections?: number;
    allow_multiple: boolean;
    items: ExtraOptionItem[];
}

export interface WeightOptionValue {
    id: number;
    weight_option_id: number;
    value: string; // decimal as string, e.g., "0.25", "0.5", "1.0"
    label?: string; // e.g., "Quarter kg", "Half kg", "1 kg"
    sort_order: number;
}

export interface WeightOption {
    id: number;
    name: string;
    unit: string; // e.g., "kg", "lb"
    values: WeightOptionValue[];
}

export interface Product {
    id: number;
    name: string;
    nameAr?: string;
    description: string;
    descriptionAr?: string;
    image?: string;
    price: number;
    base_price?: number;
    price_after_discount?: number;
    category_id?: number;
    is_active?: boolean;
    sell_by_weight?: boolean;
    weight_option?: WeightOption;
    category?: Category | string;
    categoryAr?: string;
    variants?: ProductVariant[];
    extraOption?: ExtraOption;
    rating?: number;
    reviewsCount?: number;
    badge?: string;
    badgeAr?: string;
    isNew?: boolean;
    isTrending?: boolean;
}

export interface Review {
    id: number;
    user_name: string;
    rating: number;
    comment: string;
    images?: string[];
    created_at: string;
}

export interface CartItemExtra {
    id: number;
    name: string;
    price: number;
    quantity: number;
}

export interface CartItem {
    id: number | string;
    product_id: number;
    variant_id?: number | null;
    weight_option_value_id?: number | null;
    quantity: string;
    weight_multiplier: number;
    product: {
        id: number;
        name: string;
        image?: string;
        base_price: number;
        price_after_discount?: number;
        sell_by_weight?: boolean;
        weight_option?: WeightOption;
    };
    variant?: {
        id: number;
        name: string;
        price: number | null;
    } | null;
    weight_option_value?: {
        id: number;
        value: string;
        label?: string;
    } | null;
    extras: CartItemExtra[];
    price: number;
    extras_total: number;
    subtotal: number;
}

export interface Cart {
    items: CartItem[];
    total: number;
}

export interface Address {
    id: number;
    user_id: number;
    area_id: number;
    phone_number: string;
    street: string;
    building: string;
    floor: string;
    apartment: string;
    full_address?: string;
    notes?: string;
    is_default: boolean;
    area?: {
        id: number;
        name: string;
        shipping_cost: number;
        governorate?: {
            id: number;
            name: string;
        };
    };
}

export interface Governorate {
    id: number;
    name: string;
    name_ar?: string;
    is_active: boolean;
    sort_order: number;
    areas?: Area[];
}

export interface Area {
    id: number;
    name: string;
    name_ar?: string;
    shipping_cost: number;
    governorate_id: number;
    branch_id?: number;
    is_active: boolean;
    sort_order: number;
    governorate?: Governorate;
}

export interface Branch {
    id: number;
    name: string;
    phone_number?: string;
    address?: string;
    is_active: boolean;
}

export interface OrderItemExtra {
    id: number;
    order_item_id: number;
    extra_option_item_id: number;
    extra_name: string;
    extra_price: number;
    quantity: number;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    variant_id?: number;
    weight_option_value_id?: number;
    weight_multiplier: number;
    product_name: string;
    variant_name?: string;
    quantity: string;
    unit_price: number;
    total: number;
    extras: OrderItemExtra[];
    product?: {
        id: number;
        name: string;
        sell_by_weight?: boolean;
        weight_option?: WeightOption;
    };
    weight_option_value?: {
        id: number;
        value: string;
        label?: string;
    };
}

export interface Order {
    id: number;
    order_number: string;
    merchant_order_id?: string;
    transaction_id?: string;
    user_id: number;
    branch_id: number;
    address_id?: number;
    coupon_id?: number;
    status: string;
    payment_status: string;
    payment_method: string;
    type: string;
    sub_total: number;
    tax: number;
    service: number;
    delivery_fee: number;
    discount: number;
    total: number;
    note?: string;
    created_at: string;
    updated_at: string;
    user?: User;
    branch?: Branch;
    address?: Address;
    items: OrderItem[];
}

export interface ProductShowCard {
    title: string;
    description: string;
    icon: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    settings: {
        image_placeholder: string;
        product_show_cards: ProductShowCard[];
        cod_fee: number;
    };
};


export interface Filters {
    search: string;
    category: string | string[];
    min_price?: number;
    max_price?: number;
    sort_by: string;
    sort_order: string;
}

export interface PaginatedProducts {
    data: Product[];
}

export interface MenuPageProps extends PageProps {
    products: PaginatedProducts;
    categories: Category[];
    filters: Filters;
    cartItemsCount?: number;
    searchSuggestions?: Product[];
}
