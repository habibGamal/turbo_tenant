/**
 * TypeScript Interfaces for OrderManagement Plugin
 */

import { Order, Cart, Address, Branch, Governorate } from '@/types';

export type { Order, OrderItem, OrderItemExtra } from '@/types';

export interface PaginatedOrders {
    data: Order[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
}

export interface OrdersListResponse {
    status: 'success' | 'error';
    data: PaginatedOrders;
    message?: string;
}

export interface OrderDetailResponse {
    status: 'success' | 'error';
    data: Order;
    message?: string;
}

export interface CheckoutBootstrapData {
    cart: Cart;
    addresses: Address[];
    branches: Branch[];
    governorates: Governorate[];
    is_guest: boolean;
}

export interface CheckoutDataResponse {
    status: 'success' | 'error';
    data: CheckoutBootstrapData;
    message?: string;
}

export interface GuestData {
    name: string;
    phone: string;
    phone_country_code?: string;
    email?: string;
    street?: string;
    building?: string;
    floor?: string;
    apartment?: string;
    city?: string;
    area_id?: number;
}

export interface BillingData {
    first_name?: string;
    last_name?: string;
    email?: string;
    phone_number?: string;
    apartment?: string;
    floor?: string;
    street?: string;
    building?: string;
    city?: string;
    country?: string;
    postal_code?: string;
}

export interface PlaceOrderPayload {
    branch_id: number;
    payment_method: 'card' | 'wallet' | 'cod' | 'kiosk' | 'bank_transfer' | string;
    type: 'web_delivery' | 'web_takeaway' | 'pos' | string;
    address_id?: number | null;
    coupon_id?: number | null;
    note?: string | null;
    guest_data?: GuestData | null;
    billing_data?: BillingData | null;
}

export interface PlaceOrderSuccessResponse {
    success: true;
    redirect_type: 'internal' | 'external';
    redirect_url: string;
    order_id: number;
    order_number?: string;
}

export interface PlaceOrderErrorResponse {
    success: false;
    errors: Record<string, string[]>;
}

export type PlaceOrderResponse = PlaceOrderSuccessResponse | PlaceOrderErrorResponse;
