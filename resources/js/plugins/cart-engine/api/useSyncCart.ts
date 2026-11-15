import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Cart } from '../types';
import { CART_QUERY_KEY } from './useCart';

export function useSyncCart() {
    const queryClient = useQueryClient();

    return useMutation<Cart, Error, void>({
        mutationFn: async () => {
            const { data } = await apiClient.post<ApiSuccess<Cart>>('/api/cart/sync');
            return data.data;
        },
        onSuccess: (cart) => {
            queryClient.setQueryData(CART_QUERY_KEY, cart);
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });

            const count = cart.items.length;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count } }));
        },
    });
}
