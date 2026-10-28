import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { OrderPaymentStatus } from '../types';

export function usePaymentStatus(orderId?: string | number) {
  return useQuery<OrderPaymentStatus>({
    queryKey: ['payment-status', orderId],
    queryFn: async () => {
      if (!orderId) {
        throw new Error('Order ID is required to fetch payment status');
      }
      const { data } = await apiClient.get(`/orders/${orderId}/payment-status`);
      return data.data;
    },
    enabled: !!orderId,
    staleTime: 1000 * 10,
    refetchInterval: (query) => {
      // Poll every 3 seconds if not paid yet, up to 5 times
      const data = query.state.data;
      return data?.is_paid ? false : 3000;
    },
  });
}
