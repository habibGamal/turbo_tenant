import { useMutation, useQuery } from '@tanstack/react-query';
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
