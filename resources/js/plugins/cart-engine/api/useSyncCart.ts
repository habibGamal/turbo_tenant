import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { CartApiResponse } from '../types';
import { CART_QUERY_KEY } from './useCart';

export function useSyncCart() {
    const queryClient = useQueryClient();

    return useMutation<CartApiResponse, Error, void>({
        mutationFn: async () => {
            const { data } = await apiClient.post<CartApiResponse>('/api/cart/sync');
            return data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData(CART_QUERY_KEY, data.cart);
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });

            const count = data.cart.items.length;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count } }));
        },
    });
}
