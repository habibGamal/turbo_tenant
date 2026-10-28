import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { OrderPaymentStatus } from '../types';

export function usePaymentStatus(orderId?: string | number) {
  const startTimeRef = React.useRef<number>(Date.now());

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
    staleTime: 1000 * 2,
    refetchInterval: (query) => {
      const data = query.state.data;
      const isPaid = Boolean(data?.is_paid || data?.order?.payment_status === 'completed');
      const isFailed = data?.order?.payment_status === 'failed';

      if (isPaid || isFailed) {
        return false;
      }

      if (Date.now() - startTimeRef.current >= 30000) {
        return false;
      }

      return 3000;
    },
  });
}
