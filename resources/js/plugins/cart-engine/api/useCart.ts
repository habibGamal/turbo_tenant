import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Cart } from '../types';

export const CART_QUERY_KEY = ['cart'] as const;

export function useCart() {
    return useQuery<Cart>({
        queryKey: CART_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<ApiSuccess<Cart>>('/api/cart');
            return data.data;
        },
        staleTime: 1000 * 60, // 1 minute
    });
}
