export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface Category {
    id: number;
    name: string;
    description?: string;
    image?: string;
}

export interface ProductVariant {
    id: number;
    product_id: number;
    name: string;
    price: number;
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
}

export interface ExtraOption {
    id: number;
    name: string;
    description?: string;
    is_active: boolean;
    items: ExtraOptionItem[];
}

export interface Product {
    id: number;
    name: string;
    description: string;
    image?: string;
    base_price: number;
    price_after_discount?: number;
    category_id: number;
    is_active: boolean;
    sell_by_weight: boolean;
    category?: Category;
    variants?: ProductVariant[];
    extraOption?: ExtraOption;
    rating?: number;
    reviewsCount?: number;
}

export interface Review {
    id: number;
    user_name: string;
    rating: number;
    comment: string;
    created_at: string;
}

export interface CartItemExtra {
    id: number;
    name: string;
    price: number;
}

export interface CartItem {
    id: number | string;
    product_id: number;
    variant_id?: number | null;
    quantity: string;
    product: {
        id: number;
        name: string;
        image?: string;
        base_price: number;
        price_after_discount?: number;
    };
    variant?: {
        id: number;
        name: string;
        price: number;
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

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

