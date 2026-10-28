import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { KashierConfigData } from '../types';

export function useKashierConfig(orderId?: string | number) {
  return useQuery<KashierConfigData>({
    queryKey: ['kashier-config', orderId],
    queryFn: async () => {
      if (!orderId) {
        throw new Error('Order ID is required to fetch Kashier configuration');
      }
      const { data } = await apiClient.get(`/payments/${orderId}/kashier-config`);
      return data.data;
    },
    enabled: !!orderId,
    staleTime: 1000 * 60 * 5,
  });
}
