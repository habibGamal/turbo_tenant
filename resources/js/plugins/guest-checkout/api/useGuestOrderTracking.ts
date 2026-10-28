import { useMutation, useQuery, UseQueryOptions } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import type { TrackOrderPayload, TrackOrderResponse, GuestCheckoutConfigResponse } from '../types';

/**
 * Headless TanStack Mutation Hook to track a guest order.
 */
export function useTrackGuestOrder() {
  return useMutation<TrackOrderResponse, Error, TrackOrderPayload>({
    mutationFn: async (payload: TrackOrderPayload) => {
      const { data } = await apiClient.post<TrackOrderResponse>('/api/guest-checkout/track', payload);
      return data;
    },
  });
}

/**
 * Headless TanStack Query Hook to fetch and poll guest order details.
 */
export function useGuestOrderDetails(
  payload?: TrackOrderPayload | null,
  options?: Omit<UseQueryOptions<TrackOrderResponse, Error, TrackOrderResponse>, 'queryKey' | 'queryFn'>
) {
  return useQuery<TrackOrderResponse, Error>({
    queryKey: ['guest-checkout', 'track', payload?.order_number, payload?.phone],
    queryFn: async () => {
      if (!payload?.order_number || !payload?.phone) {
        throw new Error('Order number and phone are required');
      }
      const { data } = await apiClient.post<TrackOrderResponse>('/api/guest-checkout/track', payload);
      return data;
    },
    enabled: Boolean(payload?.order_number && payload?.phone),
    staleTime: 1000 * 60 * 5,
    ...options,
  });
}

/**
 * Headless TanStack Query Hook to fetch Guest Checkout plugin configuration.
 */
export function useGuestCheckoutConfig() {
  return useQuery<GuestCheckoutConfigResponse>({
    queryKey: ['guest-checkout', 'config'],
    queryFn: async () => {
      const { data } = await apiClient.get<GuestCheckoutConfigResponse>('/api/guest-checkout');
      return data;
    },
    staleTime: 1000 * 60 * 10,
  });
}
