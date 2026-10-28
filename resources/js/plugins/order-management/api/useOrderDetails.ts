import { useQuery, UseQueryOptions } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { Order, OrderDetailResponse } from '../types';

export function useOrderDetails(
    orderId: number | string | undefined | null,
    options?: Omit<UseQueryOptions<Order, Error, Order>, 'queryKey' | 'queryFn'>
) {
    return useQuery<Order, Error>({
        queryKey: ['orders', orderId],
        queryFn: async () => {
            if (!orderId) {
                throw new Error('Order ID is required');
            }
            const { data } = await apiClient.get<OrderDetailResponse>(`/api/orders/${orderId}`);
            return data.data;
        },
        enabled: Boolean(orderId),
        staleTime: 1000 * 60 * 5, // 5 minutes
        ...options,
    });
}
