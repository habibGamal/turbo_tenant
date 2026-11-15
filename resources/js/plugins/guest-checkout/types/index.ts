import type { Order, GuestUser } from '@/types';
import type { ApiSuccess } from '@/core/api/types';

/**
 * Payload required to track a guest order.
 */
export interface TrackOrderPayload {
  order_number: string;
  phone?: string;
  token?: string;
  phone_country_code?: string;
}

/**
 * API Response returned when tracking a guest order.
 */
export type TrackOrderResponse = ApiSuccess<Order>;

/**
 * General Guest Checkout configuration response.
 */
export type GuestCheckoutConfigResponse = ApiSuccess<{
  plugin: string;
  version: string;
}>;

export type { Order, GuestUser };
