import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { emitCommerceEvent } from '@/core/analytics';
import { OrderPaymentStatus } from '../types';

export function usePaymentStatus(orderId?: string | number) {
  const startTimeRef = React.useRef<number>(Date.now());
  const hasEmittedPurchaseRef = React.useRef(false);

  const query = useQuery<OrderPaymentStatus>({
    queryKey: ['payment-status', orderId],
    queryFn: async () => {
      if (!orderId) {
        throw new Error('Order ID is required to fetch payment status');
      }
      const { data } = await apiClient.get<ApiSuccess<OrderPaymentStatus>>(`/api/orders/${orderId}/payment-status`);
      return data.data;
    },
    enabled: !!orderId,
    staleTime: 1000 * 2,
    refetchInterval: (q) => {
      const data = q.state.data;
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

  const isPaid = Boolean(query.data?.is_paid || query.data?.order?.payment_status === 'completed');

  React.useEffect(() => {
    if (!orderId || !isPaid || hasEmittedPurchaseRef.current) {
      return;
    }

    try {
      const storageKey = `meta:purchase:${orderId}`;
      const rawTracking = sessionStorage.getItem(storageKey);
      if (rawTracking) {
        sessionStorage.removeItem(storageKey);
        hasEmittedPurchaseRef.current = true;
        const tracking = JSON.parse(rawTracking);
        if (tracking && tracking.purchase_event_id) {
          emitCommerceEvent('Purchase', { tracking });
        }
      }
    } catch {
      // ignore JSON or sessionStorage errors
    }
  }, [orderId, isPaid]);

  return query;
}
