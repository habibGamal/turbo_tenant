import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { PaginatedOrders, OrdersListResponse } from '../types';

export interface UseOrdersParams {
    page?: number;
    per_page?: number;
    [key: string]: any;
}

export function useOrders(params?: UseOrdersParams, options?: { enabled?: boolean }) {
    return useQuery<PaginatedOrders>({
        queryKey: ['orders', params],
        queryFn: async () => {
            const { data } = await apiClient.get<OrdersListResponse>('/api/orders', { params });
            return {
                data: data.data,
                meta: data.meta!,
            };
        },
        enabled: options?.enabled ?? true,
        staleTime: 1000 * 60 * 2, // 2 minutes
    });
}
