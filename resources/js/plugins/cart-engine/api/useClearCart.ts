import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Cart } from '../types';
import { CART_QUERY_KEY } from './useCart';

interface MutationContext {
    previousCart?: Cart;
}

export function useClearCart() {
    const queryClient = useQueryClient();

    return useMutation<Cart, Error, void, MutationContext>({
        mutationFn: async () => {
            const { data } = await apiClient.delete<ApiSuccess<Cart>>('/api/cart');
            return data.data;
        },
        onMutate: async () => {
            await queryClient.cancelQueries({ queryKey: CART_QUERY_KEY });
            const previousCart = queryClient.getQueryData<Cart>(CART_QUERY_KEY);

            queryClient.setQueryData<Cart>(CART_QUERY_KEY, {
                items: [],
                total: 0,
            });

            return { previousCart };
        },
        onError: (_error, _variables, context) => {
            if (context?.previousCart) {
                queryClient.setQueryData(CART_QUERY_KEY, context.previousCart);
            }
        },
        onSuccess: (cart) => {
            queryClient.setQueryData(CART_QUERY_KEY, cart);
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: 0 } }));
        },
        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });
        },
    });
}
