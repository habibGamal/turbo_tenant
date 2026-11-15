/**
 * TypeScript Interfaces for OrderManagement Plugin
 */

import { Order as BaseOrder, OrderItem as BaseOrderItem, OrderItemExtra, Address, Branch, Governorate } from '@/types';
import { ApiSuccess, PaginationMeta } from '@/core/api/types';

export type { OrderItemExtra } from '@/types';

export type { OrderItemComponent } from '@/types';

export type OrderItem = BaseOrderItem;

export interface Order extends Omit<BaseOrder, 'items'> {
    items: OrderItem[];
}

export interface PaginatedOrders {
    data: Order[];
    meta: PaginationMeta;
}

export type OrdersListResponse = ApiSuccess<Order[], PaginationMeta>;
export type OrderDetailResponse = ApiSuccess<Order>;

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
    type: 'web_delivery' | 'web_takeaway';
    address_id?: number | null;
    coupon_id?: number | null;
    note?: string | null;
    guest_data?: GuestData | null;
    billing_data?: BillingData | null;
}

export interface MetaPurchaseTracking {
    purchase_event_id: string;
    order_id: string;
    value: number;
    currency: string;
    content_type: "product";
    content_ids: string[];
    contents: Array<{ id: string; quantity: number; item_price: number }>;
    num_items: number;
}

export interface PlaceOrderSuccessData {
    order_id: number;
    order_number: string;
    requires_payment: boolean;
    checkout_url: string | null;
    tracking_token?: string;
    tracking?: MetaPurchaseTracking;
}

export type PlaceOrderResponse = ApiSuccess<PlaceOrderSuccessData>;
