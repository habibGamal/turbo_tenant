import { useMutation, useQuery, UseQueryOptions } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import type { Order } from '@/types';
import type { TrackOrderPayload, TrackOrderResponse, GuestCheckoutConfigResponse } from '../types';

/**
 * Headless TanStack Mutation Hook to track a guest order.
 */
export function useTrackGuestOrder() {
  return useMutation<Order, Error, TrackOrderPayload>({
    mutationFn: async (payload: TrackOrderPayload) => {
      const { data } = await apiClient.post<TrackOrderResponse>('/api/guest-checkout/track', payload);
      return data.data;
    },
  });
}

/**
 * Headless TanStack Query Hook to fetch and poll guest order details.
 */
export function useGuestOrderDetails(
  payload?: TrackOrderPayload | null,
  options?: Omit<UseQueryOptions<Order, Error, Order>, 'queryKey' | 'queryFn'>
) {
  return useQuery<Order, Error>({
    queryKey: ['guest-checkout', 'track', payload?.order_number, payload?.token, payload?.phone],
    queryFn: async () => {
      if (!payload?.order_number || (!payload?.token && !payload?.phone)) {
        throw new Error('Order number and token or phone are required');
      }
      const { data } = await apiClient.post<TrackOrderResponse>('/api/guest-checkout/track', payload);
      return data.data;
    },
    enabled: Boolean(payload?.order_number && (payload?.token || payload?.phone)),
    staleTime: 1000 * 60 * 5,
    ...options,
  });
}

/**
 * Headless TanStack Query Hook to fetch Guest Checkout plugin configuration.
 */
export function useGuestCheckoutConfig() {
  return useQuery<{ plugin: string; version: string }>({
    queryKey: ['guest-checkout', 'config'],
    queryFn: async () => {
      const { data } = await apiClient.get<GuestCheckoutConfigResponse>('/api/guest-checkout');
      return data.data;
    },
    staleTime: 1000 * 60 * 10,
  });
}
