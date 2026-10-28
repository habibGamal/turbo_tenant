import type { Order, GuestUser } from '@/types';

/**
 * Payload required to track a guest order.
 */
export interface TrackOrderPayload {
  order_number: string;
  phone: string;
  phone_country_code?: string;
}

/**
 * API Response returned when tracking a guest order.
 */
export interface TrackOrderResponse {
  success: boolean;
  order?: Order;
  error?: string;
  errors?: Record<string, string[]>;
}

/**
 * General Guest Checkout configuration response.
 */
export interface GuestCheckoutConfigResponse {
  status: string;
  data: {
    plugin: string;
    version: string;
  };
}

export type { Order, GuestUser };
